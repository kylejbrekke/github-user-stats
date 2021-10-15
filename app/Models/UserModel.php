<?php namespace App\Models;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonSerializable;

class UserModel implements JsonSerializable {
    // constants
    const MEASUREMENTS = ['KB', 'MB', 'GB', 'TB'];
    const SI_UNITS = 1000;
    const BINARY_UNITS = 1024;

    // internal data
    private Client $_client;
    private string $_token;
    private string $_reposUrl;
    private int $_units;
    private int $_totalReposSize = 0;

    // accessible data
    private int $_statusCode = 200;
    private string $_message = 'Success';
    private array $_data = [
        'repoCount' 	=> 0,
        'stargazers' 	=> 0,
        'forks'			=> 0,
        'avgRepoSize' 	=> 0,
        'languages' 	=> array()
    ];

    /**
     * Constructs the UserModel class
     * @param string $username username of the target GitHub account
     * @param int $units units of measurement for repo size
     */
    public function __construct(string $username, int $units = self::BINARY_UNITS) {
        $this->_client = new Client();
        $this->_units = $units;
        // get access token from config; access token is vital to prevent the github api from triggering a 403 response
        $this->_token = env('GITHUB_ACCESS_TOKEN');
        try {
            // get the user info from github
            $res = $this->_client->request('GET', "https://api.github.com/users/$username",
                [
                    'headers' => [
                        'User-Agent' => 'request',
                        'Authorization' => 'Bearer ' . $this->_token
                    ]
                ]
            );

            if ($res->getStatusCode() != 200) { // forward the response if it isn't successful
                $this->_statusCode = $res->getStatusCode();
                $this->_message = $res->getBody();
                return $this;
            }

            $data = json_decode($res->getBody(), true);
            $this->_reposUrl = $data['repos_url'];
        } catch (GuzzleException $exception) { // account cannot be accessed
            $this->_statusCode = 404;
            $this->_message = 'User Not Found';
        } catch (Exception $exception) { // something else breaks
            $this->_statusCode = 500;
            $this->_message = 'Internal Server Error';
        }

        return $this;
    }

    /**
     * Gets stats for all the github repositories associated with the user model
     * @param bool $forked does not include repositories which are forked when true
     * @return void|null
     */
    public function getReposStats(bool $forked) {
        $page = 1;
        do {
            try {
                // get current page of user repositories
                $res = $this->_client->request('GET', $this->_reposUrl . "?per_page=100&page=$page",
                    [
                        'headers' => [
                            'User-Agent' => 'request',
                            'Authorization' => 'Bearer ' . $this->_token,
                        ]
                    ]
                );
                $data = json_decode($res->getBody(), true);

                if ($res->getStatusCode() != 200) { // forward the response if it isn't successful
                    $this->_statusCode = $res->getStatusCode();
                    $this->_message = $res->getBody();
                    return null;
                }

                // get stats for each repository on the page
                foreach ($data as $repo) {
                    if ($forked || !$repo['fork']) {
                        $this->_data['repoCount']++;
                        $this->_totalReposSize += $repo['size'];
                        $this->_data['stargazers'] += $repo['stargazers_count'];
                        $this->_data['forks'] += $repo['forks_count'];
                        $this->getLanguages($repo['languages_url']);
                    }
                }

                $page++;
            } catch (GuzzleException $exception) { // something broke
                $this->_statusCode = 500;
                $this->_message = 'Internal Server Error';
                return null;
            }
        } while (!sizeof($data) < 100); // a page with fewer than 100 repositories is the last page

        // do some data manipulation to get the statistics in the format we want them
        $denominator = $this->_data['repoCount'] > 0 ? $this->_data['repoCount'] : 1; // prevent division by 0
        $this->_data['avgRepoSize'] = $this->_totalReposSize / $denominator;

        // need to convert KiB to bytes for SI units
        if ($this->_units === self::SI_UNITS) {
            $this->_data['avgRepoSize'] = ($this->_data['avgRepoSize'] * self::BINARY_UNITS) / self::SI_UNITS;
        }

        // convert avg size to largest possible unit
        $index = 0;
        while ($this->_data['avgRepoSize'] > $this->_units && sizeof(self::MEASUREMENTS) !== $index) {
            $this->_data['avgRepoSize'] /= $this->_units;
            $index++;
        }

        $this->_data['avgRepoSize'] = number_format($this->_data['avgRepoSize'], 3) . ' ' . self::MEASUREMENTS[$index];
        arsort($this->_data['languages']);
    }

    /**
     * Acquires info about the languages of a repo
     * @param string $url the url of the language info for target repo in the github api
     * @throws GuzzleException
     */
    private function getLanguages(string $url) {
        // get language stats for repo
        $res = $this->_client->request('GET', $url,
            [
                'headers' => [
                    'User-Agent' => 'request',
                    'Authorization' => 'Bearer ' . $this->_token
                ]
            ]
        );
        $data = json_decode($res->getBody(), true);

        if ($res->getStatusCode() != 200) { // panic
            return;
        }

        foreach (array_keys($data) as $lang) {
            if (array_key_exists($lang, $this->_data['languages'])) { // add to the language if already present in data
                $this->_data['languages'][$lang] += $data[$lang];
            } else { // add language to list if it is not already present in data
                $this->_data['languages'][$lang] = $data[$lang];
            }
        }
    }

    public function jsonSerialize() {
        return $this->_data;
    }

    public function __get($name) {
        if ($name === 'statusCode') {
            return $this->_statusCode;
        } elseif ($name === 'message') {
            return $this->_message;
        }

        $trace = debug_backtrace();
        trigger_error('Undefined property via __get(): ' . $name . ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'], E_USER_NOTICE);
        return null;
    }
}
