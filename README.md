## Purpose
This is a simple Laravel-based API designed to query the public repository information of GitHub users. 

### Running the API Locally
You must first [create a personal access token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token) on GitHub before you can run this API locally. After doing so, you can clone this repository and assign the token to the GITHUB_ACCESS_TOKEN field in your `.env` configuration file (see `.env.example` in the project hierarchy for further details; this file may may also be copied to create a suitable `.env` config). 

Once you have configured the API locally, it can be run with by calling ```php artisan serve``` in the project from the command line.

If you do not wish to run the API locally, it may be accessed for a temporary period of time here: https://fierce-castle-33588.herokuapp.com

### Calling the API
### `GET /{username}`
Making an HTTP GET request to `/{username}` will return public repository statistics for the GitHub user associated with the target `username`.

| name     | type   | in    | description                                                                                                                                                                                                                                                                                                                                                                                                   |
|----------|--------|-------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| username | string | path  | The user name of the target GitHub account.                                                                                                                                                                                                                                                                                                                                                                   |
| forked   | bool   | query | A boolean value that determines whether the API will include results from repositories which are have been forked from a different repository. When set to `true`, the API will include forked repositories. When set to `false`, it will not. By default this value is `true`.                                                                                                                               |
| units    | string | query | A string value used to determine how the API will measure the byte size of repositories. There are two possible values for this value: `SI` and `BINARY`. When set to `BINARY`, data will be measured in orders of 1024, meaning that there will be 1024 bytes in a KB, 1024 KB in a MB, and so forth. When set to `SI`, data will be measured in orders of 1000 instead. By default, this value is `BINARY`. |
#### Shell Example
```shell
curl https://fierce-castle-33588.herokuapp.com/octocat
```
#### Response
````shell
Status: 200 OK
````
```json
{
    "repoCount": 8,
    "stargazers": 12469,
    "forks": 121777,
    "avgRepoSize": "4.101 MB", 
    "languages": {
        "Ruby": 204865, 
        "CSS": 14950,
        "HTML": 4338,
        "Shell": 910,
        "JavaScript": 48
    }
}
```
Languages returned by the response will always be provided as an ordered list with the most commonly used language (by lines of code) as the first item, and least commonly used language as the last item.
#### Example With `forked` Parameter
```shell
curl https://fierce-castle-33588.herokuapp.com/octocat?forked=false
```

#### Example With `units` Parameter
```shell
curl https://fierce-castle-33588.herokuapp.com/octocat?units=SI
```

#### Example With Both `forked` and `units` Parameters
```shell
curl https://fierce-castle-33588.herokuapp.com/octocat?forked=false&units=SI
```
