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

class GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyResponse extends \Google\Model
{
  /**
   * Not specified.
   */
  public const OVERALL_ACCESS_STATE_OVERALL_ACCESS_STATE_UNSPECIFIED = 'OVERALL_ACCESS_STATE_UNSPECIFIED';
  /**
   * The principal has the permission.
   */
  public const OVERALL_ACCESS_STATE_CAN_ACCESS = 'CAN_ACCESS';
  /**
   * The principal doesn't have the permission.
   */
  public const OVERALL_ACCESS_STATE_CANNOT_ACCESS = 'CANNOT_ACCESS';
  /**
   * The principal might have the permission, but the sender can't access all of
   * the information needed to fully evaluate the principal's access.
   */
  public const OVERALL_ACCESS_STATE_UNKNOWN_INFO = 'UNKNOWN_INFO';
  /**
   * The principal might have the permission, but Policy Troubleshooter can't
   * fully evaluate the principal's access because the sender didn't provide the
   * required context to evaluate the condition.
   */
  public const OVERALL_ACCESS_STATE_UNKNOWN_CONDITIONAL = 'UNKNOWN_CONDITIONAL';
  protected $accessTupleType = GoogleCloudPolicytroubleshooterIamV3AccessTuple::class;
  protected $accessTupleDataType = '';
  protected $allowPolicyExplanationType = GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation::class;
  protected $allowPolicyExplanationDataType = '';
  protected $denyPolicyExplanationType = GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation::class;
  protected $denyPolicyExplanationDataType = '';
  /**
   * Indicates whether the principal has the specified permission for the
   * specified resource, based on evaluating all types of the applicable IAM
   * policies.
   *
   * @var string
   */
  public $overallAccessState;

  /**
   * The access tuple from the request, including any provided context used to
   * evaluate the condition.
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
  /**
   * An explanation of how the applicable IAM allow policies affect the final
   * access state.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation $allowPolicyExplanation
   */
  public function setAllowPolicyExplanation(GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation $allowPolicyExplanation)
  {
    $this->allowPolicyExplanation = $allowPolicyExplanation;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation
   */
  public function getAllowPolicyExplanation()
  {
    return $this->allowPolicyExplanation;
  }
  /**
   * An explanation of how the applicable IAM deny policies affect the final
   * access state.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation $denyPolicyExplanation
   */
  public function setDenyPolicyExplanation(GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation $denyPolicyExplanation)
  {
    $this->denyPolicyExplanation = $denyPolicyExplanation;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation
   */
  public function getDenyPolicyExplanation()
  {
    return $this->denyPolicyExplanation;
  }
  /**
   * Indicates whether the principal has the specified permission for the
   * specified resource, based on evaluating all types of the applicable IAM
   * policies.
   *
   * Accepted values: OVERALL_ACCESS_STATE_UNSPECIFIED, CAN_ACCESS,
   * CANNOT_ACCESS, UNKNOWN_INFO, UNKNOWN_CONDITIONAL
   *
   * @param self::OVERALL_ACCESS_STATE_* $overallAccessState
   */
  public function setOverallAccessState($overallAccessState)
  {
    $this->overallAccessState = $overallAccessState;
  }
  /**
   * @return self::OVERALL_ACCESS_STATE_*
   */
  public function getOverallAccessState()
  {
    return $this->overallAccessState;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyResponse::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3TroubleshootIamPolicyResponse');
