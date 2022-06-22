<?php

require './config.inc.php';
require './class/OAuth.class.php';
require './class/FacebookOAuth.class.php';
require './class/DiscordOAuth.class.php';
require './class/CustomOAuth.class.php';

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
