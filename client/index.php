<?php

require './config.inc.php';
require './class/OAuth.class.php';
require './class/FacebookOAuth.class.php';
require './class/DiscordOAuth.class.php';
require './class/CustomOAuth.class.php';
require './class/TwitchOAuth.class.php';
require './class/GithubOAuth.class.php';

$providers = [
  'Twitch' => [
    'class' => new TwitchOAuth(
      TWITCH_CLIENT_ID,
      'https://id.twitch.tv/oauth2/authorize',
      '',
      TWITCH_CLIENT_SECRET,
      'https://id.twitch.tv/oauth2/token',
      'https://api.twitch.tv/helix/users',
      'POST'
    ),
    'prefix' => 'twitch_',
  ],
  'Discord' => [
    'class' => new DiscordOAuth(
      DISCORD_CLIENT_ID,
      'http://discord.com/api/oauth2/authorize',
      'identify email',
      DISCORD_CLIENT_SECRET,
      'https://discord.com/api/oauth2/token',
      'https://discord.com/api/users/@me',
      'POST'
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
    ),
    'prefix' => 'fb_',
  ],
  'Github' => [
    'class' => new GithubOAuth(
      GITHUB_CLIENT_ID,
      'https://github.com/login/oauth/authorize',
      'user',
      GITHUB_CLIENT_SECRET,
      'https://github.com/login/oauth/access_token',
      'https://api.github.com/user',
    ),
    'prefix' => 'gh_',
  ],
  'Custom Oauth Server' => [
    'class' => new CustomOAuth(
      OAUTH_CLIENT_ID,
      'http://localhost:8080/auth',
      'basic',
      OAUTH_CLIENT_SECRET,
      'http://server:8080/token',
      'http://server:8080/me',
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
        $oauth['class']->retriveToken();
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
