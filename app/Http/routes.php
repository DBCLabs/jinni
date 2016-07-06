<?php

use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

//facebook sends a one time GET request to this route as verification
Route::get('/fbNewMessage', function (Request $request) {
    Log::info('GET - fbNewMessage');
    $verify = env('HUB_VERIFY_TOKEN');
    if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $verify) {
        Log::info('Valid subscription verification request received');
        return $request->query('hub_challenge');
    }
});

//route to which facebook will send new messages
Route::post('/fbNewMessage', 'FbConversationCallbackController@processConversationCallbackRequest');

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
