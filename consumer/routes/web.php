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

Route::get('/redirect', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));

    $query = http_build_query([
        'client_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
        'redirect_uri' => 'http://localhost:8001/callback',
        'response_type' => 'code',
        'scope' => '',
        'state' => $state,
    ]);

    return redirect('http://localhost:8000/oauth/authorize?'.$query);
});

Route::get('/callback', function (Request $request) {
   $state = $request->session()->pull('state');

   throw_unless(
       strlen($state) > 0 && $state === $request->state,
       InvalidArgumentException::class
   );

    $http = new GuzzleHttp\Client;

    $response = $http->post('http://localhost:8000/oauth/token', [
        'form_params' => [
            'grant_type' => 'authorization_code',
            'client_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
            'client_secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
            'redirect_uri' => 'http://localhost:8001/callback',
            'code' => $request->code,
        ],
    ]);

    $result = json_decode((string) $response->getBody(), true);
    $accessToken = $result['access_token'];

    $response = $http->get('http://localhost:8000/api/teste', [
        'headers' => [
            'Authorization' => "Bearer {$accessToken}",
            'Accept'     => 'application/json',
        ]
    ]);

    return $response->getBody();
});
