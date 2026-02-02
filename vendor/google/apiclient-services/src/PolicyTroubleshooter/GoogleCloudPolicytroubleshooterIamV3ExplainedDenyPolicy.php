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

class GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy extends \Google\Collection
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
  protected $collection_key = 'ruleExplanations';
  /**
   * Required. Indicates whether _this policy_ denies the specified permission
   * to the specified principal for the specified resource. This field does
   * _not_ indicate whether the principal actually has the permission for the
   * resource. There might be another policy that overrides this policy. To
   * determine whether the principal actually has the permission, use the
   * `overall_access_state` field in the TroubleshootIamPolicyResponse.
   *
   * @var string
   */
  public $denyAccessState;
  protected $policyType = GoogleIamV2Policy::class;
  protected $policyDataType = '';
  /**
   * The relevance of this policy to the overall access state in the
   * TroubleshootIamPolicyResponse. If the sender of the request does not have
   * access to the policy, this field is omitted.
   *
   * @var string
   */
  public $relevance;
  protected $ruleExplanationsType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation::class;
  protected $ruleExplanationsDataType = 'array';

  /**
   * Required. Indicates whether _this policy_ denies the specified permission
   * to the specified principal for the specified resource. This field does
   * _not_ indicate whether the principal actually has the permission for the
   * resource. There might be another policy that overrides this policy. To
   * determine whether the principal actually has the permission, use the
   * `overall_access_state` field in the TroubleshootIamPolicyResponse.
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
   * The IAM deny policy attached to the resource. If the sender of the request
   * does not have access to the policy, this field is omitted.
   *
   * @param GoogleIamV2Policy $policy
   */
  public function setPolicy(GoogleIamV2Policy $policy)
  {
    $this->policy = $policy;
  }
  /**
   * @return GoogleIamV2Policy
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
  /**
   * Details about how each rule in the policy affects the principal's inability
   * to use the permission for the resource. The order of the deny rule matches
   * the order of the rules in the deny policy. If the sender of the request
   * does not have access to the policy, this field is omitted.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation[] $ruleExplanations
   */
  public function setRuleExplanations($ruleExplanations)
  {
    $this->ruleExplanations = $ruleExplanations;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation[]
   */
  public function getRuleExplanations()
  {
    return $this->ruleExplanations;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ExplainedDenyPolicy');
