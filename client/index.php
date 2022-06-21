<?php

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
    return $this->oAuthUri . '?' . $this->generateAccessTokenQueryParams();
  }
}

class DiscordOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "ds_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
  }
};

class FacebookOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "fb_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
  }
};

class CustomOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope, string $clientSecret, string $accessTokenUri)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "custom_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
  }
};

$providers = [
  'Discord' => [
    'class' => new DiscordOAuth(
      '988797982344372284',
      'http://discord.com/api/oauth2/authorize',
      'identify',
      'XbTq51Re5g-dxrQ85FKjBuZBmYDMPVTP',
    ),
    'prefix' => 'ds_',
  ],
  'Facebook' => [
    'class' => new FacebookOAuth(
      '504031208137914',
      'http://www.facebook.com/v14.0/dialog/oauth',
      'fc5e25661fe961ab85d130779357541e',
    ),
    'prefix' => 'fb_',
  ],
  'Custom Oauth Server' => [
    'class' => new CustomOAuth(
      '621f59c71bc35',
      'http://localhost:8080/auth',
      'basic',
      '621f59c71bc36',
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
    foreach ($GLOBALS['providers'] as $oauth) {
      if (str_starts_with($state, $oauth['prefix'])) {
        die($oauth['prefix']);

        $response = file_get_contents($oauth['class']->getAccessTokenUri());
        $token = json_decode($response, true);
      }
    }
    if (str_starts_with($state, "ds_")) {
      die("Discord OAuth is not supported yet");
    } elseif (str_starts_with($state, "fb_")) {
      die("Facebook OAuth is not supported yet");
    } elseif (str_starts_with($state, "custom_")) {
      die("Custom OAuth is not supported yet");
    } else {
      throw new Exception("Invalid state");
    }

    $specifParams = [
      'code' => $code,
      'grant_type' => 'authorization_code',
    ];
  }

  $queryParams = http_build_query(array_merge([
    'client_id' => $,
    'client_secret' => OAUTH_CLIENT_SECRET,
    'redirect_uri' => 'http://localhost:8081/callback',
  ], $specifParams));
  $response = file_get_contents("http://server:8080/token?{$queryParams}");
  $token = json_decode($response, true);

  $context = stream_context_create([
    'http' => [
      'header' => "Authorization: Bearer {$token['access_token']}"
    ]
  ]);
  $response = file_get_contents("http://server:8080/me", false, $context);
  $user = json_decode($response, true);
  echo "Hello {$user['lastname']} {$user['firstname']}";
}

function fbcallback()
{
  ["code" => $code, "state" => $state] = $_GET;

  $specifParams = [
    'code' => $code,
    'grant_type' => 'authorization_code',
  ];

  $queryParams = http_build_query(array_merge([
    'client_id' => FACEBOOK_CLIENT_ID,
    'client_secret' => FACEBOOK_CLIENT_SECRET,
    'redirect_uri' => 'http://localhost:8081/fb_callback',
  ], $specifParams));
  $response = file_get_contents("https://graph.facebook.com/v2.10/oauth/access_token?{$queryParams}");
  $token = json_decode($response, true);

  $context = stream_context_create([
    'http' => [
      'header' => "Authorization: Bearer {$token['access_token']}"
    ]
  ]);
  $response = file_get_contents("https://graph.facebook.com/v2.10/me", false, $context);
  $user = json_decode($response, true);
  echo "Hello {$user['name']}";
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
    case '/fb_callback':
        fbcallback();
        break;
    case '/ds_callback':
        dscallback();
        break;
    default:
        http_response_code(404);
        break;
}
