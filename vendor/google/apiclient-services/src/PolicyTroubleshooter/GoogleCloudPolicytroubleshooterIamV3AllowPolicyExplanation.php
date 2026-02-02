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

class GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation extends \Google\Collection
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
  protected $collection_key = 'explainedPolicies';
  /**
   * Indicates whether the principal has the specified permission for the
   * specified resource, based on evaluating all applicable IAM allow policies.
   *
   * @var string
   */
  public $allowAccessState;
  protected $explainedPoliciesType = GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy::class;
  protected $explainedPoliciesDataType = 'array';
  /**
   * The relevance of the allow policy type to the overall access state.
   *
   * @var string
   */
  public $relevance;

  /**
   * Indicates whether the principal has the specified permission for the
   * specified resource, based on evaluating all applicable IAM allow policies.
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
   * List of IAM allow policies that were evaluated to check the principal's
   * permissions, with annotations to indicate how each policy contributed to
   * the final result. The list of policies includes the policy for the resource
   * itself, as well as allow policies that are inherited from higher levels of
   * the resource hierarchy, including the organization, the folder, and the
   * project. To learn more about the resource hierarchy, see
   * https://cloud.google.com/iam/help/resource-hierarchy.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy[] $explainedPolicies
   */
  public function setExplainedPolicies($explainedPolicies)
  {
    $this->explainedPolicies = $explainedPolicies;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ExplainedAllowPolicy[]
   */
  public function getExplainedPolicies()
  {
    return $this->explainedPolicies;
  }
  /**
   * The relevance of the allow policy type to the overall access state.
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
class_alias(GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3AllowPolicyExplanation');
