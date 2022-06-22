<?php

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
