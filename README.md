# Passport Laravel

## Uso com oauth
Temos projetos Laravel: consumer e provider. O Provider tem o passport como autorização, o Consumer é utilizado para pedir acesso ao Provider. Cada um rodará em porta separada.

Para o Consumer:
````
cd /consumer
composer install
copy .env.example .env
php artisan key:generate
php artisan serve --port 8001
````

Para o Provider:
````
cd /provider
composer install
npm install && npm run dev
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan passport:install
php artisan serve
````

### Pedindo autorização com o Consumer

Para fazer com que o Consumer consiga acessar algo da aplicação do Provider, crie um OAuth Clients em /passport no Provider e informe o callback do Consumer /callback.

No Consumer a rota que inicia a autorização é /loginIntoAnotherApp. Você será redirecionado para o /ouath/authorize do Provider. Após autorizar, você será redirecionado novamente para a sua aplicação /callback que no caso retornará um json.

Como é pedido a autorização:
````
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
````

Após receber a autorização, essa rota de callback é chamada:

````
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
});
````

Nesse caso o tipo do grant_type foi usado como authorization_code, mas você utilizar algumas formas: password, client_credentials. Semelhante as formas abaixo.


### Recebendo token de outras formas do Provider
Para receber o token com usuário e senha: POST /oauth/token. Rode antes: php artisan passport:client --password que gerará um novo client id e secret. Não é usado o mesmo criado em Ouath clients.

````
{
	"grant_type" : "password",
	"client_id" : "",
	"client_secret" : "",
	"username" : "",
	"password" : ""
}
````

Para receber o token somente com client id e secret sem ter usuário e senha: POST /oauth/token. Rode antes: php artisan passport:client --client que gerará também um novo client id e secret. Nesse grant type, é quando é preciso que execute algo agendado por meio de rotinas por exemplo.
````
//Receba o token
{
	"grant_type" : "client_credentials",
	"client_id" : "",
	"client_secret" : "",
    "scope": ""
}
//Depois requiste para
GET /api/teste-rotina
````
Outra forma também é criar um Personal Access Tokens em /passport no Provider, com ele é só utilizar no header para requisitar.

## Uso sem ouath
Utilizando o passport para autenticação, cadastrar um usuário. Pode-se fazer algo assim:

Uma controller
````
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;

class PassportAuthController extends Controller
{
    /**
     * Registration
     */
    public function register(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|min:4',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        $token = $user->createToken('LaravelAuthApp')->accessToken;

        return response()->json(['token' => $token], 200);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $data = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if (auth()->attempt($data)) {
            $token = auth()->user()->createToken('LaravelAuthApp')->accessToken;
            return response()->json(['token' => $token], 200);
        } else {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
    }
}
````

e as rotas:
````
Route::post('register', [PassportAuthController::class, 'register'])->name('register');
Route::post('login', [PassportAuthController::class, 'login'])->name('login');
````
