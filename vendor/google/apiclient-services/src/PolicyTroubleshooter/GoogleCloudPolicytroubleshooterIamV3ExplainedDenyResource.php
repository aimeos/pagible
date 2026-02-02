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

class GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource extends \Google\Collection
{
  /**
   * Not specified.
   */
  public const DENY_ACCESS_STATE_DENY_ACCESS_STATE_UNSPECIFIED = 'DENY_ACCESS_STATE_UNSPECIFIED';
  /**
   * The deny policy denies the principal the permission.
   */
  public const DENY_ACCESS_STATE_DENY_ACCESS_STATE_DENIED = 'DENY_ACCESS_STATE_DENIED';
  /**
   * The deny policy doesn't deny the principal the permission.
   */
  public const DENY_ACCESS_STATE_DENY_ACCESS_STATE_NOT_DENIED = 'DENY_ACCESS_STATE_NOT_DENIED';
  /**
   * The deny policy denies the principal the permission if a condition
   * expression evaluates to `true`. However, the sender of the request didn't
   * provide enough context for Policy Troubleshooter to evaluate the condition
   * expression.
   */
  public const DENY_ACCESS_STATE_DENY_ACCESS_STATE_UNKNOWN_CONDITIONAL = 'DENY_ACCESS_STATE_UNKNOWN_CONDITIONAL';
  /**
   * The sender of the request does not have access to all of the deny policies
   * that Policy Troubleshooter needs to evaluate the principal's access.
   */
  public const DENY_ACCESS_STATE_DENY_ACCESS_STATE_UNKNOWN_INFO = 'DENY_ACCESS_STATE_UNKNOWN_INFO';
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
  protected $collection_key = 'explainedPolicies';
  /**
   * Required. Indicates whether any policies attached to _this resource_ deny
   * the specific permission to the specified principal for the specified
   * resource. This field does _not_ indicate whether the principal actually has
   * the permission for the resource. There might be another policy that
   * overrides this policy. To determine whether the principal actually has the
   * permission, use the `overall_access_state` field in the
   * TroubleshootIamPolicyResponse.
   *
   * @var string
   */
  public $denyAccessState;
  protected $explainedPoliciesType = GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy::class;
  protected $explainedPoliciesDataType = 'array';
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
  /**
   * The relevance of this policy to the overall access state in the
   * TroubleshootIamPolicyResponse. If the sender of the request does not have
   * access to the policy, this field is omitted.
   *
   * @var string
   */
  public $relevance;

  /**
   * Required. Indicates whether any policies attached to _this resource_ deny
   * the specific permission to the specified principal for the specified
   * resource. This field does _not_ indicate whether the principal actually has
   * the permission for the resource. There might be another policy that
   * overrides this policy. To determine whether the principal actually has the
   * permission, use the `overall_access_state` field in the
   * TroubleshootIamPolicyResponse.
   *
   * Accepted values: DENY_ACCESS_STATE_UNSPECIFIED, DENY_ACCESS_STATE_DENIED,
   * DENY_ACCESS_STATE_NOT_DENIED, DENY_ACCESS_STATE_UNKNOWN_CONDITIONAL,
   * DENY_ACCESS_STATE_UNKNOWN_INFO
   *
   * @param self::DENY_ACCESS_STATE_* $denyAccessState
   */
  public function setDenyAccessState($denyAccessState)
  {
    $this->denyAccessState = $denyAccessState;
  }
  /**
   * @return self::DENY_ACCESS_STATE_*
   */
  public function getDenyAccessState()
  {
    return $this->denyAccessState;
  }
  /**
   * List of IAM deny policies that were evaluated to check the principal's
   * denied permissions, with annotations to indicate how each policy
   * contributed to the final result.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy[] $explainedPolicies
   */
  public function setExplainedPolicies($explainedPolicies)
  {
    $this->explainedPolicies = $explainedPolicies;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy[]
   */
  public function getExplainedPolicies()
  {
    return $this->explainedPolicies;
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
class_alias(GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource');
