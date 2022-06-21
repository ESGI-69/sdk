<?php

class OAuth
{
  protected $clientId;
  protected $redirectUri = 'http://localhost:8081/callback';
  protected $responseType = 'code';
  protected $scope;
  protected $state;
  protected $oAuthUri;

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

  protected function generateAuthorizationUri(): string
  {
    $queryParams = $this->generateQueryParams();
    $authorizationUri = $this->oAuthUri . '?' . $queryParams;
    return $authorizationUri;
  }
}
