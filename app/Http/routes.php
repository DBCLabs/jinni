<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fbNewMessage', function (Request $request) {
    Log::info('GET - fbNewMessage');
    $verify = env('HUB_VERIFY_TOKEN');
    if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $verify) {
        echo $request->query('hub_challenge');
        Log::info('Valid subscription verification request received');
    }
});

Route::post('/fbNewMessage', 'FbNewMessageController@processMessage');

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web']], function () {
    //
});
