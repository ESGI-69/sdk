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
  protected $token;
  protected $userInfoUri;
  protected $userInfos;
  protected $retriveTokenMethod = 'GET';

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

  protected function retriveTokenContext()
  {
    $context = stream_context_create([
      'http' => [
        'method' => $this->retriveTokenMethod,
        'header' => [
          'Content-Type: application/x-www-form-urlencoded',
          'Content-Length: ' . strlen($this->generateAccessTokenQueryParams()),
        ],
        'content' => $this->generateAccessTokenQueryParams()
      ],
    ]);
    return $context;
  }

  public function retriveToken()
  {
    $response = file_get_contents($this->getAccessTokenUri(), false, $this->retriveTokenContext());
    $this->setToken(json_decode($response, true)['access_token']);
  }

  protected function setToken(string $token)
  {
    $this->token = $token;
  }

  protected function getToken()
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
        'header' => [
          "Authorization: Bearer " . $this->getToken() . "",
          "Client-ID: " . $this->clientId . "",
        ],
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
