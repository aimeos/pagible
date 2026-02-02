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

class GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy extends \Google\Collection
{
  /**
   * Not specified.
   */
  public const ALLOW_ACCESS_STATE_ALLOW_ACCESS_STATE_UNSPECIFIED = 'ALLOW_ACCESS_STATE_UNSPECIFIED';
  /**
   * The allow policy gives the principal the permission.
   */
  public const ALLOW_ACCESS_STATE_ALLOW_ACCESS_STATE_GRANTED = 'ALLOW_ACCESS_STATE_GRANTED';
  /**
   * The allow policy doesn't give the principal the permission.
   */
  public const ALLOW_ACCESS_STATE_ALLOW_ACCESS_STATE_NOT_GRANTED = 'ALLOW_ACCESS_STATE_NOT_GRANTED';
  /**
   * The allow policy gives the principal the permission if a condition
   * expression evaluate to `true`. However, the sender of the request didn't
   * provide enough context for Policy Troubleshooter to evaluate the condition
   * expression.
   */
  public const ALLOW_ACCESS_STATE_ALLOW_ACCESS_STATE_UNKNOWN_CONDITIONAL = 'ALLOW_ACCESS_STATE_UNKNOWN_CONDITIONAL';
  /**
   * The sender of the request doesn't have access to all of the allow policies
   * that Policy Troubleshooter needs to evaluate the principal's access.
   */
  public const ALLOW_ACCESS_STATE_ALLOW_ACCESS_STATE_UNKNOWN_INFO = 'ALLOW_ACCESS_STATE_UNKNOWN_INFO';
  /**
   * Not specified.
   */
  public const RELEVANCE_HEURISTIC_RELEVANCE_UNSPECIFIED = 'HEURISTIC_RELEVANCE_UNSPECIFIED';
  /**
   * The data point has a limited effect on the result. Changing the data point
   * is unlikely to affect the overall determination.
   */
  public const RELEVANCE_HEURISTIC_RELEVANCE_NORMAL = 'HEURISTIC_RELEVANCE_NORMAL';
  /**
   * The data point has a strong effect on the result. Changing the data point
   * is likely to affect the overall determination.
   */
  public const RELEVANCE_HEURISTIC_RELEVANCE_HIGH = 'HEURISTIC_RELEVANCE_HIGH';
  protected $collection_key = 'bindingExplanations';
  /**
   * Required. Indicates whether _this policy_ provides the specified permission
   * to the specified principal for the specified resource. This field does
   * _not_ indicate whether the principal actually has the permission for the
   * resource. There might be another policy that overrides this policy. To
   * determine whether the principal actually has the permission, use the
   * `overall_access_state` field in the TroubleshootIamPolicyResponse.
   *
   * @var string
   */
  public $allowAccessState;
  protected $bindingExplanationsType = GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation::class;
  protected $bindingExplanationsDataType = 'array';
  /**
   * The full resource name that identifies the resource. For example,
   * `//compute.googleapis.com/projects/my-project/zones/us-
   * central1-a/instances/my-instance`. If the sender of the request does not
   * have access to the policy, this field is omitted. For examples of full
   * resource names for Google Cloud services, see
   * https://cloud.google.com/iam/help/troubleshooter/full-resource-names.
   *
   * @var string
   */
  public $fullResourceName;
  protected $policyType = GoogleIamV1Policy::class;
  protected $policyDataType = '';
  /**
   * The relevance of this policy to the overall access state in the
   * TroubleshootIamPolicyResponse. If the sender of the request does not have
   * access to the policy, this field is omitted.
   *
   * @var string
   */
  public $relevance;

  /**
   * Required. Indicates whether _this policy_ provides the specified permission
   * to the specified principal for the specified resource. This field does
   * _not_ indicate whether the principal actually has the permission for the
   * resource. There might be another policy that overrides this policy. To
   * determine whether the principal actually has the permission, use the
   * `overall_access_state` field in the TroubleshootIamPolicyResponse.
   *
   * Accepted values: ALLOW_ACCESS_STATE_UNSPECIFIED,
   * ALLOW_ACCESS_STATE_GRANTED, ALLOW_ACCESS_STATE_NOT_GRANTED,
   * ALLOW_ACCESS_STATE_UNKNOWN_CONDITIONAL, ALLOW_ACCESS_STATE_UNKNOWN_INFO
   *
   * @param self::ALLOW_ACCESS_STATE_* $allowAccessState
   */
  public function setAllowAccessState($allowAccessState)
  {
    $this->allowAccessState = $allowAccessState;
  }
  /**
   * @return self::ALLOW_ACCESS_STATE_*
   */
  public function getAllowAccessState()
  {
    return $this->allowAccessState;
  }
  /**
   * Details about how each role binding in the policy affects the principal's
   * ability, or inability, to use the permission for the resource. The order of
   * the role bindings matches the role binding order in the policy. If the
   * sender of the request does not have access to the policy, this field is
   * omitted.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation[] $bindingExplanations
   */
  public function setBindingExplanations($bindingExplanations)
  {
    $this->bindingExplanations = $bindingExplanations;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation[]
   */
  public function getBindingExplanations()
  {
    return $this->bindingExplanations;
  }
  /**
   * The full resource name that identifies the resource. For example,
   * `//compute.googleapis.com/projects/my-project/zones/us-
   * central1-a/instances/my-instance`. If the sender of the request does not
   * have access to the policy, this field is omitted. For examples of full
   * resource names for Google Cloud services, see
   * https://cloud.google.com/iam/help/troubleshooter/full-resource-names.
   *
   * @param string $fullResourceName
   */
  public function setFullResourceName($fullResourceName)
  {
    $this->fullResourceName = $fullResourceName;
  }
  /**
   * @return string
   */
  public function getFullResourceName()
  {
    return $this->fullResourceName;
  }
  /**
   * The IAM allow policy attached to the resource. If the sender of the request
   * does not have access to the policy, this field is empty.
   *
   * @param GoogleIamV1Policy $policy
   */
  public function setPolicy(GoogleIamV1Policy $policy)
  {
    $this->policy = $policy;
  }
  /**
   * @return GoogleIamV1Policy
   */
  public function getPolicy()
  {
    return $this->policy;
  }
  /**
   * The relevance of this policy to the overall access state in the
   * TroubleshootIamPolicyResponse. If the sender of the request does not have
   * access to the policy, this field is omitted.
   *
   * Accepted values: HEURISTIC_RELEVANCE_UNSPECIFIED,
   * HEURISTIC_RELEVANCE_NORMAL, HEURISTIC_RELEVANCE_HIGH
   *
   * @param self::RELEVANCE_* $relevance
   */
  public function setRelevance($relevance)
  {
    $this->relevance = $relevance;
  }
  /**
   * @return self::RELEVANCE_*
   */
  public function getRelevance()
  {
    return $this->relevance;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy');
