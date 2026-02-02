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

class GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation extends \Google\Model
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
  /**
   * Not specified.
   */
  public const ROLE_PERMISSION_ROLE_PERMISSION_INCLUSION_STATE_UNSPECIFIED = 'ROLE_PERMISSION_INCLUSION_STATE_UNSPECIFIED';
  /**
   * The permission is included in the role.
   */
  public const ROLE_PERMISSION_ROLE_PERMISSION_INCLUDED = 'ROLE_PERMISSION_INCLUDED';
  /**
   * The permission is not included in the role.
   */
  public const ROLE_PERMISSION_ROLE_PERMISSION_NOT_INCLUDED = 'ROLE_PERMISSION_NOT_INCLUDED';
  /**
   * The sender of the request is not allowed to access the role definition.
   */
  public const ROLE_PERMISSION_ROLE_PERMISSION_UNKNOWN_INFO = 'ROLE_PERMISSION_UNKNOWN_INFO';
  /**
   * Not specified.
   */
  public const ROLE_PERMISSION_RELEVANCE_HEURISTIC_RELEVANCE_UNSPECIFIED = 'HEURISTIC_RELEVANCE_UNSPECIFIED';
  /**
   * The data point has a limited effect on the result. Changing the data point
   * is unlikely to affect the overall determination.
   */
  public const ROLE_PERMISSION_RELEVANCE_HEURISTIC_RELEVANCE_NORMAL = 'HEURISTIC_RELEVANCE_NORMAL';
  /**
   * The data point has a strong effect on the result. Changing the data point
   * is likely to affect the overall determination.
   */
  public const ROLE_PERMISSION_RELEVANCE_HEURISTIC_RELEVANCE_HIGH = 'HEURISTIC_RELEVANCE_HIGH';
  /**
   * Required. Indicates whether _this role binding_ gives the specified
   * permission to the specified principal on the specified resource. This field
   * does _not_ indicate whether the principal actually has the permission on
   * the resource. There might be another role binding that overrides this role
   * binding. To determine whether the principal actually has the permission,
   * use the `overall_access_state` field in the TroubleshootIamPolicyResponse.
   *
   * @var string
   */
  public $allowAccessState;
  protected $combinedMembershipType = GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership::class;
  protected $combinedMembershipDataType = '';
  protected $conditionType = GoogleTypeExpr::class;
  protected $conditionDataType = '';
  protected $conditionExplanationType = GoogleCloudPolicytroubleshooterIamV3ConditionExplanation::class;
  protected $conditionExplanationDataType = '';
  protected $membershipsType = GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership::class;
  protected $membershipsDataType = 'map';
  /**
   * The relevance of this role binding to the overall determination for the
   * entire policy.
   *
   * @var string
   */
  public $relevance;
  /**
   * The role that this role binding grants. For example, `roles/compute.admin`.
   * For a complete list of predefined IAM roles, as well as the permissions in
   * each role, see https://cloud.google.com/iam/help/roles/reference.
   *
   * @var string
   */
  public $role;
  /**
   * Indicates whether the role granted by this role binding contains the
   * specified permission.
   *
   * @var string
   */
  public $rolePermission;
  /**
   * The relevance of the permission's existence, or nonexistence, in the role
   * to the overall determination for the entire policy.
   *
   * @var string
   */
  public $rolePermissionRelevance;

  /**
   * Required. Indicates whether _this role binding_ gives the specified
   * permission to the specified principal on the specified resource. This field
   * does _not_ indicate whether the principal actually has the permission on
   * the resource. There might be another role binding that overrides this role
   * binding. To determine whether the principal actually has the permission,
   * use the `overall_access_state` field in the TroubleshootIamPolicyResponse.
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
   * The combined result of all memberships. Indicates if the principal is
   * included in any role binding, either directly or indirectly.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership $combinedMembership
   */
  public function setCombinedMembership(GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership $combinedMembership)
  {
    $this->combinedMembership = $combinedMembership;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership
   */
  public function getCombinedMembership()
  {
    return $this->combinedMembership;
  }
  /**
   * A condition expression that specifies when the role binding grants access.
   * To learn about IAM Conditions, see
   * https://cloud.google.com/iam/help/conditions/overview.
   *
   * @param GoogleTypeExpr $condition
   */
  public function setCondition(GoogleTypeExpr $condition)
  {
    $this->condition = $condition;
  }
  /**
   * @return GoogleTypeExpr
   */
  public function getCondition()
  {
    return $this->condition;
  }
  /**
   * Condition evaluation state for this role binding.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionExplanation $conditionExplanation
   */
  public function setConditionExplanation(GoogleCloudPolicytroubleshooterIamV3ConditionExplanation $conditionExplanation)
  {
    $this->conditionExplanation = $conditionExplanation;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionExplanation
   */
  public function getConditionExplanation()
  {
    return $this->conditionExplanation;
  }
  /**
   * Indicates whether each role binding includes the principal specified in the
   * request, either directly or indirectly. Each key identifies a principal in
   * the role binding, and each value indicates whether the principal in the
   * role binding includes the principal in the request. For example, suppose
   * that a role binding includes the following principals: *
   * `user:alice@example.com` * `group:product-eng@example.com` You want to
   * troubleshoot access for `user:bob@example.com`. This user is a member of
   * the group `group:product-eng@example.com`. For the first principal in the
   * role binding, the key is `user:alice@example.com`, and the `membership`
   * field in the value is set to `NOT_INCLUDED`. For the second principal in
   * the role binding, the key is `group:product-eng@example.com`, and the
   * `membership` field in the value is set to `INCLUDED`.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership[] $memberships
   */
  public function setMemberships($memberships)
  {
    $this->memberships = $memberships;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanationAnnotatedAllowMembership[]
   */
  public function getMemberships()
  {
    return $this->memberships;
  }
  /**
   * The relevance of this role binding to the overall determination for the
   * entire policy.
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
   * The role that this role binding grants. For example, `roles/compute.admin`.
   * For a complete list of predefined IAM roles, as well as the permissions in
   * each role, see https://cloud.google.com/iam/help/roles/reference.
   *
   * @param string $role
   */
  public function setRole($role)
  {
    $this->role = $role;
  }
  /**
   * @return string
   */
  public function getRole()
  {
    return $this->role;
  }
  /**
   * Indicates whether the role granted by this role binding contains the
   * specified permission.
   *
   * Accepted values: ROLE_PERMISSION_INCLUSION_STATE_UNSPECIFIED,
   * ROLE_PERMISSION_INCLUDED, ROLE_PERMISSION_NOT_INCLUDED,
   * ROLE_PERMISSION_UNKNOWN_INFO
   *
   * @param self::ROLE_PERMISSION_* $rolePermission
   */
  public function setRolePermission($rolePermission)
  {
    $this->rolePermission = $rolePermission;
  }
  /**
   * @return self::ROLE_PERMISSION_*
   */
  public function getRolePermission()
  {
    return $this->rolePermission;
  }
  /**
   * The relevance of the permission's existence, or nonexistence, in the role
   * to the overall determination for the entire policy.
   *
   * Accepted values: HEURISTIC_RELEVANCE_UNSPECIFIED,
   * HEURISTIC_RELEVANCE_NORMAL, HEURISTIC_RELEVANCE_HIGH
   *
   * @param self::ROLE_PERMISSION_RELEVANCE_* $rolePermissionRelevance
   */
  public function setRolePermissionRelevance($rolePermissionRelevance)
  {
    $this->rolePermissionRelevance = $rolePermissionRelevance;
  }
  /**
   * @return self::ROLE_PERMISSION_RELEVANCE_*
   */
  public function getRolePermissionRelevance()
  {
    return $this->rolePermissionRelevance;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3AllowBindingExplanation');
