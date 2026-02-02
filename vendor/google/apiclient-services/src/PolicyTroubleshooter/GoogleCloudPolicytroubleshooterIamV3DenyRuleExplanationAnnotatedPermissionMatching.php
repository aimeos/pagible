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

class GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching extends \Google\Model
{
  /**
   * Not specified.
   */
  public const PERMISSION_MATCHING_STATE_PERMISSION_PATTERN_MATCHING_STATE_UNSPECIFIED = 'PERMISSION_PATTERN_MATCHING_STATE_UNSPECIFIED';
  /**
   * The permission in the request matches the permission in the policy.
   */
  public const PERMISSION_MATCHING_STATE_PERMISSION_PATTERN_MATCHED = 'PERMISSION_PATTERN_MATCHED';
  /**
   * The permission in the request matches the permission in the policy.
   */
  public const PERMISSION_MATCHING_STATE_PERMISSION_PATTERN_NOT_MATCHED = 'PERMISSION_PATTERN_NOT_MATCHED';
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
  /**
   * Indicates whether the permission in the request is denied by the deny rule.
   *
   * @var string
   */
  public $permissionMatchingState;
  /**
   * The relevance of the permission status to the overall determination for the
   * rule.
   *
   * @var string
   */
  public $relevance;

  /**
   * Indicates whether the permission in the request is denied by the deny rule.
   *
   * Accepted values: PERMISSION_PATTERN_MATCHING_STATE_UNSPECIFIED,
   * PERMISSION_PATTERN_MATCHED, PERMISSION_PATTERN_NOT_MATCHED
   *
   * @param self::PERMISSION_MATCHING_STATE_* $permissionMatchingState
   */
  public function setPermissionMatchingState($permissionMatchingState)
  {
    $this->permissionMatchingState = $permissionMatchingState;
  }
  /**
   * @return self::PERMISSION_MATCHING_STATE_*
   */
  public function getPermissionMatchingState()
  {
    return $this->permissionMatchingState;
  }
  /**
   * The relevance of the permission status to the overall determination for the
   * rule.
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
class_alias(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching');
