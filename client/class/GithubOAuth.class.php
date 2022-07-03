<?php

class GithubOAuth extends OAuth
{
  public function __construct(
    string $clientId,
    string $oAuthUri,
    string $scope,
    string $clientSecret,
    string $accessTokenUri,
    string $userInfoUri,
    string $retriveTokenMethod = 'POST'
  ) {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "gh_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
    $this->clientSecret = $clientSecret;
    $this->accessTokenUri = $accessTokenUri;
    $this->userInfoUri = $userInfoUri;
    $this->retriveTokenMethod = $retriveTokenMethod;
  }

  public function __toString()
  {
    return "Bonjour " . ($this->userInfos['login']) . " !";
  }
};
