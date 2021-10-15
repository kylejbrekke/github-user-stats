<?php namespace App\Http\Controllers;

use App\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller {

    /**
     * @param Request $request an HTTP request containing the optional parameters for the API
     * @param string $username username of the target GitHub account
     * @return Response
     */
    public static function userAction(Request $request, string $username): Response {
        // get optional parameters from request
        $forked = !($request->input('forked') === 'false');
        $units = $request->input('units') === 'SI' ? UserModel::SI_UNITS : UserModel::BINARY_UNITS;

        // instantiate user model and get initial data
        $userModel = new UserModel($username, $units);
        if ($userModel->statusCode !== 200) {
            return new Response($userModel->message, $userModel->statusCode);
        }

        // acquire repository statistics for user model
        $userModel->getReposStats($forked);
        if ($userModel->statusCode !== 200) {
            return new Response($userModel->message, $userModel->statusCode);
        }

        // return stats as JSON
        return new Response(
            json_encode($userModel),
            200,
            ['Content-Type' => 'application/json']
        );
    }

}
