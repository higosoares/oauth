# Passport Laravel

## Uso
Temos projetos Laravel: consumer e provider. O Provider tem o passport com como autenticação, o Consumer é utilizado para pedir acesso ao Provider. Cada um rodará em porta separada.

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
php artisan serve
````

### Pedindo autorização com o Consumer

Para fazer com que o Consumer consiga acessar algo da aplicação do Provider, crie um OAuth Clients em /passport no Provider e informe o callback do Consumer /callback.

No Consumer a rota que inicia a autorização é /redirect. Você será redirecionado para o /ouath/authorize do Provider. Após autorizar, você será redirecionado novamente para a sua aplicação /callback que no caso retornará um json.

Como é pedido a autorização:
````
Route::get('/redirect', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));
    $urlProvider = env('APP_URL_PROVIDER');

    $query = http_build_query([
        'client_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
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
            'client_id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
            'client_secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
            'redirect_uri' => route('callback'),
            'code' => $request->code,
        ],
    ]);

    $result = json_decode((string) $response->getBody(), true);
    $accessToken = $result['access_token'];

    $response = $http->get("$urlProvider/api/teste", [
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
Para receber o token com usuário e senha: POST /oauth/token. Rode antes: php artisan passport:client --password. No entanto, se você ativer esse modo e pelo o que eu testei, mesmo que você requisite com o grant_type=client_credentials e retorne um token, sempre dá sem autorização. Então parece que ao ativar com password, será somente com password.

````
{
	"grant_type" : "password",
	"client_id" : "",
	"client_secret" : "",
	"username" : "",
	"password" : ""
}
````

Para receber o token somente com client id e secret sem ter usuário e senha: POST /oauth/token
````
{
	"grant_type" : "client_credentials",
	"client_id" : "",
	"client_secret" : ""
}
````
Outra forma também é criar um Personal Access Tokens em /passport no Provider, com ele é só utilizar no header para requisitar.