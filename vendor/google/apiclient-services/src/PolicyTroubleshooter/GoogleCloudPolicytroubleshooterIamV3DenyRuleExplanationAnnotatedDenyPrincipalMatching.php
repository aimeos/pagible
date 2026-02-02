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

class GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching extends \Google\Model
{
  /**
   * Not specified.
   */
  public const MEMBERSHIP_MEMBERSHIP_MATCHING_STATE_UNSPECIFIED = 'MEMBERSHIP_MATCHING_STATE_UNSPECIFIED';
  /**
   * The principal in the request matches the principal in the policy. The
   * principal can be included directly or indirectly: * A principal is included
   * directly if that principal is listed in the role binding. * A principal is
   * included indirectly if that principal is in a Google group, Google
   * Workspace account, or Cloud Identity domain that is listed in the policy.
   */
  public const MEMBERSHIP_MEMBERSHIP_MATCHED = 'MEMBERSHIP_MATCHED';
  /**
   * The principal in the request doesn't match the principal in the policy.
   */
  public const MEMBERSHIP_MEMBERSHIP_NOT_MATCHED = 'MEMBERSHIP_NOT_MATCHED';
  /**
   * The principal in the policy is a group or domain, and the sender of the
   * request doesn't have permission to view whether the principal in the
   * request is a member of the group or domain.
   */
  public const MEMBERSHIP_MEMBERSHIP_UNKNOWN_INFO = 'MEMBERSHIP_UNKNOWN_INFO';
  /**
   * The principal is an unsupported type.
   */
  public const MEMBERSHIP_MEMBERSHIP_UNKNOWN_UNSUPPORTED = 'MEMBERSHIP_UNKNOWN_UNSUPPORTED';
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
   * Indicates whether the principal is listed as a denied principal in the deny
   * rule, either directly or through membership in a principal set.
   *
   * @var string
   */
  public $membership;
  /**
   * The relevance of the principal's status to the overall determination for
   * the role binding.
   *
   * @var string
   */
  public $relevance;

  /**
   * Indicates whether the principal is listed as a denied principal in the deny
   * rule, either directly or through membership in a principal set.
   *
   * Accepted values: MEMBERSHIP_MATCHING_STATE_UNSPECIFIED, MEMBERSHIP_MATCHED,
   * MEMBERSHIP_NOT_MATCHED, MEMBERSHIP_UNKNOWN_INFO,
   * MEMBERSHIP_UNKNOWN_UNSUPPORTED
   *
   * @param self::MEMBERSHIP_* $membership
   */
  public function setMembership($membership)
  {
    $this->membership = $membership;
  }
  /**
   * @return self::MEMBERSHIP_*
   */
  public function getMembership()
  {
    return $this->membership;
  }
  /**
   * The relevance of the principal's status to the overall determination for
   * the role binding.
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
class_alias(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching');
