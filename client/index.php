<?php

require './config.inc.php';

class OAuth
{
  protected $clientId;
  protected $redirectUri = 'http://localhost:8081/callback';
  protected $responseType = 'code';
  protected $scope;
  protected $state;
  protected $oAuthUri;
  protected $clientSecret;
  protected $accessTokenUri;
  protected $tokenArray;
  protected $userInfoUri;
  protected $userInfos;

  protected function generateQueryParams(): string
  {
    $queryParams = http_build_query([
      'client_id' => $this->clientId,
      'redirect_uri' => $this->redirectUri,
      'response_type' => $this->responseType,
      'scope' => $this->scope,
      "state" => $this->state,
    ]);
    return $queryParams;
  }

  public function getAuthorizationUri(): string
  {
    $queryParams = $this->generateQueryParams();
    $authorizationUri = $this->oAuthUri . '?' . $queryParams;
    return $authorizationUri;
  }

  protected function generateAccessTokenQueryParams(): string
  {
    $queryParams = http_build_query([
      'client_id' => $this->clientId,
      'client_secret' => $this->clientSecret,
      'code' => $this->code,
      'redirect_uri' => $this->redirectUri,
      'grant_type' => 'authorization_code',
    ]);
    return $queryParams;
  }

  public function getAccessTokenUri(): string
  {
    return $this->accessTokenUri . '?' . $this->generateAccessTokenQueryParams();
  }

  public function setCode(string $code)
  {
    $this->code = $code;
  }

  public function setToken(string $token)
  {
    $this->token = $token;
  }

  public function getToken()
  {
    return $this->token;
  }

  public function setUserInfos($userInfos)
  {
    $this->userInfos = $userInfos;
  }

  public function getUserInfosContext()
  {
    return stream_context_create([
      'http' => [
        'header' => "Authorization: Bearer " . $this->getToken() . "",
      ]
    ]);
  }

  public function getUserInfosUri(): string
  {
    return $this->userInfoUri;
  }

  public function __toString()
  {
    return json_encode($this->userInfos);
  }
}

class DiscordOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri, string $userInfoUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "ds_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
    $this->userInfoUri = $userInfoUri;
  }
};

class FacebookOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri, string $userInfoUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "fb_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
    $this->userInfoUri = $userInfoUri;
  }

  public function __toString()
  {
    return "Bonjour " . ($this->userInfos['name']) . " !";
  }
};

class CustomOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri, string $userInfoUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "custom_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
    $this->userInfoUri = $userInfoUri;
  }
};

$providers = [
  'Discord' => [
    'class' => new DiscordOAuth(
      DISCORD_CLIENT_ID,
      'http://discord.com/api/oauth2/authorize',
      'identify',
      DISCORD_CLIENT_SECRET,
      'https://discord.com/api/oauth2/token',
      'https://discord.com/api/users/@me',
      'DISCORD'
    ),
    'prefix' => 'ds_',
  ],
  'Facebook' => [
    'class' => new FacebookOAuth(
      FACEBOOK_CLIENT_ID,
      'http://www.facebook.com/v14.0/dialog/oauth',
      'email',
      FACEBOOK_CLIENT_SECRET,
      'https://graph.facebook.com/v2.10/oauth/access_token',
      'https://graph.facebook.com/v2.10/me',
      'FACEBOOK'
    ),
    'prefix' => 'fb_',
  ],
  'Custom Oauth Server' => [
    'class' => new CustomOAuth(
      OAUTH_CLIENT_ID,
      'http://localhost:8080/auth',
      'basic',
      OAUTH_CLIENT_SECRET,
      'http://server:8080/token',
      'http://server:8080/me',
      'CUSTOM'
    ),
    'prefix' => 'custom_',
  ],
];

function login()
{
  echo "
    <form action='/callback' method='post'>
        <input type='text' name='username'/>
        <input type='password' name='password'/>
        <input type='submit' value='Login'/>
    </form>
  ";

  foreach ($GLOBALS['providers'] as $provider => $oauth) {
    echo "<a href=" . $oauth['class']->getAuthorizationUri() . ">Login with {$provider}</a>";
    echo "<br>";
  }
}

// Exchange code for token then get user info
function callback()
{
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ["username" => $username, "password" => $password] = $_POST;
    $specifParams = [
      'username' => $username,
      'password' => $password,
      'grant_type' => 'password',
    ];
  } else {
    ["code" => $code, "state" => $state] = $_GET;
    $providerFound = false;
    foreach ($GLOBALS['providers'] as $oauth) {
      $providerFound = true;
      if (str_starts_with($state, $oauth['prefix'])) {
        $oauth['class']->setCode($code);

        $response = file_get_contents($oauth['class']->getAccessTokenUri());
        $token = json_decode($response, true);

        $oauth['class']->setToken($token['access_token']);
        $response = file_get_contents($oauth['class']->getUserInfosUri(), false, $oauth['class']->getUserInfosContext());
        $user = json_decode($response, true);
        $oauth['class']->setUserInfos($user);
        die($oauth['class']);
      }
    }
    if (!$providerFound) {
      die("Provider not found");
    }
  }
}

function dscallback()
{
  ["code" => $code, "state" => $state] = $_GET;

  $specifParams = [
    'code' => $code,
    'grant_type' => 'authorization_code',
  ];

  $queryParams = http_build_query(array_merge([
    'client_id' => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'redirect_uri' => 'http://localhost:8081/ds_callback',
  ], $specifParams));

  $context = stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header' => "Content-type: application/x-www-form-urlencoded\r\n"
        . "Content-Length: " . strlen($queryParams) . "\r\n",
      'content' => $queryParams
    ]
  ]);

  $response = file_get_contents("https://discord.com/api/oauth2/token", false, $context);
  $token = json_decode($response, true);
  // $user = "https://discord.com/api/oauth2/users/@me";

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => "Authorization: Bearer {$token['access_token']}"
    ]
  ]);
  $response = file_get_contents("https://discord.com/api/oauth2/@me", false, $context);
  $user = json_decode($response, true);

  echo "Hello {$user['user']['username']}";
}

$route = $_SERVER["REQUEST_URI"];
switch (strtok($route, "?")) {
  case '/login':
    login();
    break;
  case '/callback':
    callback();
    break;
  default:
    http_response_code(404);
    break;
}
