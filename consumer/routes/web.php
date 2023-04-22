<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/loginIntoAnotherApp', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));
    $urlProvider = env('APP_URL_PROVIDER');

    $query = http_build_query([
        'client_id' => env('PASSPORT_CLIENT_ID'),
        'redirect_uri' => route('callback'),
        'response_type' => 'code',
        'scope' => '',
        'state' => $state,
    ]);

    return redirect("$urlProvider/oauth/authorize?$query");
});

Route::get('/callback', function (Request $request) {
   $state = $request->session()->pull('state');

   throw_unless(
       strlen($state) > 0 && $state === $request->state,
       InvalidArgumentException::class
   );

    $http = new GuzzleHttp\Client;
    $urlProvider = env('APP_URL_PROVIDER');

    $response = $http->post("$urlProvider/oauth/token", [
        'form_params' => [
            'grant_type' => 'authorization_code',
            'client_id' => env('PASSPORT_CLIENT_ID'),
            'client_secret' => env('PASSPORT_CLIENT_SECRET'),
            'redirect_uri' => route('callback'),
            'code' => $request->code,
        ],
    ]);

    $result = json_decode((string) $response->getBody(), true);
    $accessToken = $result['access_token'];

    $response = $http->get("$urlProvider/api/user", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
            'Accept'     => 'application/json',
        ]
    ]);

    return $response->getBody();
})->name('callback');
