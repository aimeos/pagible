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

class GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation extends \Google\Collection
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
  protected $collection_key = 'explainedResources';
  /**
   * Indicates whether the principal is denied the specified permission for the
   * specified resource, based on evaluating all applicable IAM deny policies.
   *
   * @var string
   */
  public $denyAccessState;
  protected $explainedResourcesType = GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource::class;
  protected $explainedResourcesDataType = 'array';
  /**
   * Indicates whether the permission to troubleshoot is supported in deny
   * policies.
   *
   * @var bool
   */
  public $permissionDeniable;
  /**
   * The relevance of the deny policy result to the overall access state.
   *
   * @var string
   */
  public $relevance;

  /**
   * Indicates whether the principal is denied the specified permission for the
   * specified resource, based on evaluating all applicable IAM deny policies.
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
   * List of resources with IAM deny policies that were evaluated to check the
   * principal's denied permissions, with annotations to indicate how each
   * policy contributed to the final result. The list of resources includes the
   * policy for the resource itself, as well as policies that are inherited from
   * higher levels of the resource hierarchy, including the organization, the
   * folder, and the project. The order of the resources starts from the
   * resource and climbs up the resource hierarchy. To learn more about the
   * resource hierarchy, see https://cloud.google.com/iam/help/resource-
   * hierarchy.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource[] $explainedResources
   */
  public function setExplainedResources($explainedResources)
  {
    $this->explainedResources = $explainedResources;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ExplainedDenyResource[]
   */
  public function getExplainedResources()
  {
    return $this->explainedResources;
  }
  /**
   * Indicates whether the permission to troubleshoot is supported in deny
   * policies.
   *
   * @param bool $permissionDeniable
   */
  public function setPermissionDeniable($permissionDeniable)
  {
    $this->permissionDeniable = $permissionDeniable;
  }
  /**
   * @return bool
   */
  public function getPermissionDeniable()
  {
    return $this->permissionDeniable;
  }
  /**
   * The relevance of the deny policy result to the overall access state.
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
class_alias(GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3DenyPolicyExplanation');
