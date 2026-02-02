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

class GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyRequest extends \Google\Model
{
  protected $accessTupleType = GoogleCloudPolicytroubleshooterIamV3AccessTuple::class;
  protected $accessTupleDataType = '';

  /**
   * The information to use for checking whether a principal has a permission
   * for a resource.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3AccessTuple $accessTuple
   */
  public function setAccessTuple(GoogleCloudPolicytroubleshooterIamV3AccessTuple $accessTuple)
  {
    $this->accessTuple = $accessTuple;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3AccessTuple
   */
  public function getAccessTuple()
  {
    return $this->accessTuple;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyRequest::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyRequest');
