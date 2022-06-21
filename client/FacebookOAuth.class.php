<?php

require './OAuth.class.php';

class FacebookOAuth extends OAuth
{
  public function __construct(string $clientId, string $oAuthUri, string $scope)
  {
    $this->clientId = $clientId;
    $this->scope = $scope;
    $this->state = "fb_" . bin2hex(random_bytes(16));
    $this->oAuthUri = $oAuthUri;
  }
};
