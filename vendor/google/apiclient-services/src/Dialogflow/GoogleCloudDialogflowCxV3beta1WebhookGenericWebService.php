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

class GoogleCloudDialogflowCxV3beta1WebhookGenericWebService extends \Google\Collection
{
  public const HTTP_METHOD_HTTP_METHOD_UNSPECIFIED = 'HTTP_METHOD_UNSPECIFIED';
  public const HTTP_METHOD_POST = 'POST';
  public const HTTP_METHOD_GET = 'GET';
  public const HTTP_METHOD_HEAD = 'HEAD';
  public const HTTP_METHOD_PUT = 'PUT';
  public const HTTP_METHOD_DELETE = 'DELETE';
  public const HTTP_METHOD_PATCH = 'PATCH';
  public const HTTP_METHOD_OPTIONS = 'OPTIONS';
  public const SERVICE_AGENT_AUTH_SERVICE_AGENT_AUTH_UNSPECIFIED = 'SERVICE_AGENT_AUTH_UNSPECIFIED';
  public const SERVICE_AGENT_AUTH_NONE = 'NONE';
  public const SERVICE_AGENT_AUTH_ID_TOKEN = 'ID_TOKEN';
  public const SERVICE_AGENT_AUTH_ACCESS_TOKEN = 'ACCESS_TOKEN';
  public const WEBHOOK_TYPE_WEBHOOK_TYPE_UNSPECIFIED = 'WEBHOOK_TYPE_UNSPECIFIED';
  public const WEBHOOK_TYPE_STANDARD = 'STANDARD';
  public const WEBHOOK_TYPE_FLEXIBLE = 'FLEXIBLE';
  protected $collection_key = 'allowedCaCerts';
  /**
   * @var string[]
   */
  public $allowedCaCerts;
  /**
   * @var string
   */
  public $httpMethod;
  protected $oauthConfigType = GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceOAuthConfig::class;
  protected $oauthConfigDataType = '';
  /**
   * @var string[]
   */
  public $parameterMapping;
  /**
   * @deprecated
   * @var string
   */
  public $password;
  /**
   * @var string
   */
  public $requestBody;
  /**
   * @var string[]
   */
  public $requestHeaders;
  /**
   * @var string
   */
  public $secretVersionForUsernamePassword;
  protected $secretVersionsForRequestHeadersType = GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceSecretVersionHeaderValue::class;
  protected $secretVersionsForRequestHeadersDataType = 'map';
  protected $serviceAccountAuthConfigType = GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceServiceAccountAuthConfig::class;
  protected $serviceAccountAuthConfigDataType = '';
  /**
   * @var string
   */
  public $serviceAgentAuth;
  /**
   * @var string
   */
  public $uri;
  /**
   * @deprecated
   * @var string
   */
  public $username;
  /**
   * @var string
   */
  public $webhookType;

  /**
   * @param string[] $allowedCaCerts
   */
  public function setAllowedCaCerts($allowedCaCerts)
  {
    $this->allowedCaCerts = $allowedCaCerts;
  }
  /**
   * @return string[]
   */
  public function getAllowedCaCerts()
  {
    return $this->allowedCaCerts;
  }
  /**
   * @param self::HTTP_METHOD_* $httpMethod
   */
  public function setHttpMethod($httpMethod)
  {
    $this->httpMethod = $httpMethod;
  }
  /**
   * @return self::HTTP_METHOD_*
   */
  public function getHttpMethod()
  {
    return $this->httpMethod;
  }
  /**
   * @param GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceOAuthConfig $oauthConfig
   */
  public function setOauthConfig(GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceOAuthConfig $oauthConfig)
  {
    $this->oauthConfig = $oauthConfig;
  }
  /**
   * @return GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceOAuthConfig
   */
  public function getOauthConfig()
  {
    return $this->oauthConfig;
  }
  /**
   * @param string[] $parameterMapping
   */
  public function setParameterMapping($parameterMapping)
  {
    $this->parameterMapping = $parameterMapping;
  }
  /**
   * @return string[]
   */
  public function getParameterMapping()
  {
    return $this->parameterMapping;
  }
  /**
   * @deprecated
   * @param string $password
   */
  public function setPassword($password)
  {
    $this->password = $password;
  }
  /**
   * @deprecated
   * @return string
   */
  public function getPassword()
  {
    return $this->password;
  }
  /**
   * @param string $requestBody
   */
  public function setRequestBody($requestBody)
  {
    $this->requestBody = $requestBody;
  }
  /**
   * @return string
   */
  public function getRequestBody()
  {
    return $this->requestBody;
  }
  /**
   * @param string[] $requestHeaders
   */
  public function setRequestHeaders($requestHeaders)
  {
    $this->requestHeaders = $requestHeaders;
  }
  /**
   * @return string[]
   */
  public function getRequestHeaders()
  {
    return $this->requestHeaders;
  }
  /**
   * @param string $secretVersionForUsernamePassword
   */
  public function setSecretVersionForUsernamePassword($secretVersionForUsernamePassword)
  {
    $this->secretVersionForUsernamePassword = $secretVersionForUsernamePassword;
  }
  /**
   * @return string
   */
  public function getSecretVersionForUsernamePassword()
  {
    return $this->secretVersionForUsernamePassword;
  }
  /**
   * @param GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceSecretVersionHeaderValue[] $secretVersionsForRequestHeaders
   */
  public function setSecretVersionsForRequestHeaders($secretVersionsForRequestHeaders)
  {
    $this->secretVersionsForRequestHeaders = $secretVersionsForRequestHeaders;
  }
  /**
   * @return GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceSecretVersionHeaderValue[]
   */
  public function getSecretVersionsForRequestHeaders()
  {
    return $this->secretVersionsForRequestHeaders;
  }
  /**
   * @param GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceServiceAccountAuthConfig $serviceAccountAuthConfig
   */
  public function setServiceAccountAuthConfig(GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceServiceAccountAuthConfig $serviceAccountAuthConfig)
  {
    $this->serviceAccountAuthConfig = $serviceAccountAuthConfig;
  }
  /**
   * @return GoogleCloudDialogflowCxV3beta1WebhookGenericWebServiceServiceAccountAuthConfig
   */
  public function getServiceAccountAuthConfig()
  {
    return $this->serviceAccountAuthConfig;
  }
  /**
   * @param self::SERVICE_AGENT_AUTH_* $serviceAgentAuth
   */
  public function setServiceAgentAuth($serviceAgentAuth)
  {
    $this->serviceAgentAuth = $serviceAgentAuth;
  }
  /**
   * @return self::SERVICE_AGENT_AUTH_*
   */
  public function getServiceAgentAuth()
  {
    return $this->serviceAgentAuth;
  }
  /**
   * @param string $uri
   */
  public function setUri($uri)
  {
    $this->uri = $uri;
  }
  /**
   * @return string
   */
  public function getUri()
  {
    return $this->uri;
  }
  /**
   * @deprecated
   * @param string $username
   */
  public function setUsername($username)
  {
    $this->username = $username;
  }
  /**
   * @deprecated
   * @return string
   */
  public function getUsername()
  {
    return $this->username;
  }
  /**
   * @param self::WEBHOOK_TYPE_* $webhookType
   */
  public function setWebhookType($webhookType)
  {
    $this->webhookType = $webhookType;
  }
  /**
   * @return self::WEBHOOK_TYPE_*
   */
  public function getWebhookType()
  {
    return $this->webhookType;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3beta1WebhookGenericWebService::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3beta1WebhookGenericWebService');
