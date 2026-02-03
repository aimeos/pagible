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

namespace Google\Service\GKEHub;

class WorkloadIdentityIdentityProviderStateDetail extends \Google\Model
{
  /**
   * Unknown state.
   */
  public const CODE_IDENTITY_PROVIDER_STATE_UNSPECIFIED = 'IDENTITY_PROVIDER_STATE_UNSPECIFIED';
  /**
   * The Identity Provider was created/updated successfully.
   */
  public const CODE_IDENTITY_PROVIDER_STATE_OK = 'IDENTITY_PROVIDER_STATE_OK';
  /**
   * The Identity Provider was not created/updated successfully. The error
   * message is in the description field.
   */
  public const CODE_IDENTITY_PROVIDER_STATE_ERROR = 'IDENTITY_PROVIDER_STATE_ERROR';
  /**
   * The state of the Identity Provider.
   *
   * @var string
   */
  public $code;
  /**
   * A human-readable description of the current state or returned error.
   *
   * @var string
   */
  public $description;

  /**
   * The state of the Identity Provider.
   *
   * Accepted values: IDENTITY_PROVIDER_STATE_UNSPECIFIED,
   * IDENTITY_PROVIDER_STATE_OK, IDENTITY_PROVIDER_STATE_ERROR
   *
   * @param self::CODE_* $code
   */
  public function setCode($code)
  {
    $this->code = $code;
  }
  /**
   * @return self::CODE_*
   */
  public function getCode()
  {
    return $this->code;
  }
  /**
   * A human-readable description of the current state or returned error.
   *
   * @param string $description
   */
  public function setDescription($description)
  {
    $this->description = $description;
  }
  /**
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(WorkloadIdentityIdentityProviderStateDetail::class, 'Google_Service_GKEHub_WorkloadIdentityIdentityProviderStateDetail');
