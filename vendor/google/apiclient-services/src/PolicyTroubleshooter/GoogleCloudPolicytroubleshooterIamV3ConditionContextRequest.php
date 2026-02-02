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

namespace Google\Service\PolicyTroubleshooter;

class GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest extends \Google\Model
{
  /**
   * Optional. The timestamp when the destination service receives the first
   * byte of the request.
   *
   * @var string
   */
  public $receiveTime;

  /**
   * Optional. The timestamp when the destination service receives the first
   * byte of the request.
   *
   * @param string $receiveTime
   */
  public function setReceiveTime($receiveTime)
  {
    $this->receiveTime = $receiveTime;
  }
  /**
   * @return string
   */
  public function getReceiveTime()
  {
    return $this->receiveTime;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest');
