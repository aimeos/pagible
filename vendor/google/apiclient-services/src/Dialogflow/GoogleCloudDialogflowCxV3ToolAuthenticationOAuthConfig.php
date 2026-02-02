<?php
/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Google\Service\Dialogflow;

class GoogleCloudDialogflowCxV3ToolAuthenticationOAuthConfig extends \Google\Collection
{
  public const OAUTH_GRANT_TYPE_OAUTH_GRANT_TYPE_UNSPECIFIED = 'OAUTH_GRANT_TYPE_UNSPECIFIED';
  public const OAUTH_GRANT_TYPE_CLIENT_CREDENTIAL = 'CLIENT_CREDENTIAL';
  protected $collection_key = 'scopes';
  /**
   * @var string
   */
  public $clientId;
  /**
   * @var string
   */
  public $clientSecret;
  /**
   * @var string
   */
  public $oauthGrantType;
  /**
   * @var string[]
   */
  public $scopes;
  /**
   * @var string
   */
  public $secretVersionForClientSecret;
  /**
   * @var string
   */
  public $tokenEndpoint;

  /**
   * @param string $clientId
   */
  public function setClientId($clientId)
  {
    $this->clientId = $clientId;
  }
  /**
   * @return string
   */
  public function getClientId()
  {
    return $this->clientId;
  }
  /**
   * @param string $clientSecret
   */
  public function setClientSecret($clientSecret)
  {
    $this->clientSecret = $clientSecret;
  }
  /**
   * @return string
   */
  public function getClientSecret()
  {
    return $this->clientSecret;
  }
  /**
   * @param self::OAUTH_GRANT_TYPE_* $oauthGrantType
   */
  public function setOauthGrantType($oauthGrantType)
  {
    $this->oauthGrantType = $oauthGrantType;
  }
  /**
   * @return self::OAUTH_GRANT_TYPE_*
   */
  public function getOauthGrantType()
  {
    return $this->oauthGrantType;
  }
  /**
   * @param string[] $scopes
   */
  public function setScopes($scopes)
  {
    $this->scopes = $scopes;
  }
  /**
   * @return string[]
   */
  public function getScopes()
  {
    return $this->scopes;
  }
  /**
   * @param string $secretVersionForClientSecret
   */
  public function setSecretVersionForClientSecret($secretVersionForClientSecret)
  {
    $this->secretVersionForClientSecret = $secretVersionForClientSecret;
  }
  /**
   * @return string
   */
  public function getSecretVersionForClientSecret()
  {
    return $this->secretVersionForClientSecret;
  }
  /**
   * @param string $tokenEndpoint
   */
  public function setTokenEndpoint($tokenEndpoint)
  {
    $this->tokenEndpoint = $tokenEndpoint;
  }
  /**
   * @return string
   */
  public function getTokenEndpoint()
  {
    return $this->tokenEndpoint;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3ToolAuthenticationOAuthConfig::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3ToolAuthenticationOAuthConfig');
