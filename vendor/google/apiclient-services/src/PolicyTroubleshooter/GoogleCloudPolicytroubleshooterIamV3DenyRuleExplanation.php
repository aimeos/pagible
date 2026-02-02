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

class GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation extends \Google\Model
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
  protected $combinedDeniedPermissionType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching::class;
  protected $combinedDeniedPermissionDataType = '';
  protected $combinedDeniedPrincipalType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching::class;
  protected $combinedDeniedPrincipalDataType = '';
  protected $combinedExceptionPermissionType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching::class;
  protected $combinedExceptionPermissionDataType = '';
  protected $combinedExceptionPrincipalType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching::class;
  protected $combinedExceptionPrincipalDataType = '';
  protected $conditionType = GoogleTypeExpr::class;
  protected $conditionDataType = '';
  protected $conditionExplanationType = GoogleCloudPolicytroubleshooterIamV3ConditionExplanation::class;
  protected $conditionExplanationDataType = '';
  protected $deniedPermissionsType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching::class;
  protected $deniedPermissionsDataType = 'map';
  protected $deniedPrincipalsType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching::class;
  protected $deniedPrincipalsDataType = 'map';
  /**
   * Required. Indicates whether _this rule_ denies the specified permission to
   * the specified principal for the specified resource. This field does _not_
   * indicate whether the principal is actually denied on the permission for the
   * resource. There might be another rule that overrides this rule. To
   * determine whether the principal actually has the permission, use the
   * `overall_access_state` field in the TroubleshootIamPolicyResponse.
   *
   * @var string
   */
  public $denyAccessState;
  protected $exceptionPermissionsType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching::class;
  protected $exceptionPermissionsDataType = 'map';
  protected $exceptionPrincipalsType = GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching::class;
  protected $exceptionPrincipalsDataType = 'map';
  /**
   * The relevance of this role binding to the overall determination for the
   * entire policy.
   *
   * @var string
   */
  public $relevance;

  /**
   * Indicates whether the permission in the request is listed as a denied
   * permission in the deny rule.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching $combinedDeniedPermission
   */
  public function setCombinedDeniedPermission(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching $combinedDeniedPermission)
  {
    $this->combinedDeniedPermission = $combinedDeniedPermission;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching
   */
  public function getCombinedDeniedPermission()
  {
    return $this->combinedDeniedPermission;
  }
  /**
   * Indicates whether the principal is listed as a denied principal in the deny
   * rule, either directly or through membership in a principal set.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching $combinedDeniedPrincipal
   */
  public function setCombinedDeniedPrincipal(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching $combinedDeniedPrincipal)
  {
    $this->combinedDeniedPrincipal = $combinedDeniedPrincipal;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching
   */
  public function getCombinedDeniedPrincipal()
  {
    return $this->combinedDeniedPrincipal;
  }
  /**
   * Indicates whether the permission in the request is listed as an exception
   * permission in the deny rule.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching $combinedExceptionPermission
   */
  public function setCombinedExceptionPermission(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching $combinedExceptionPermission)
  {
    $this->combinedExceptionPermission = $combinedExceptionPermission;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching
   */
  public function getCombinedExceptionPermission()
  {
    return $this->combinedExceptionPermission;
  }
  /**
   * Indicates whether the principal is listed as an exception principal in the
   * deny rule, either directly or through membership in a principal set.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching $combinedExceptionPrincipal
   */
  public function setCombinedExceptionPrincipal(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching $combinedExceptionPrincipal)
  {
    $this->combinedExceptionPrincipal = $combinedExceptionPrincipal;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching
   */
  public function getCombinedExceptionPrincipal()
  {
    return $this->combinedExceptionPrincipal;
  }
  /**
   * A condition expression that specifies when the deny rule denies the
   * principal access. To learn about IAM Conditions, see
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
   * Lists all denied permissions in the deny rule and indicates whether each
   * permission matches the permission in the request. Each key identifies a
   * denied permission in the rule, and each value indicates whether the denied
   * permission matches the permission in the request.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching[] $deniedPermissions
   */
  public function setDeniedPermissions($deniedPermissions)
  {
    $this->deniedPermissions = $deniedPermissions;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching[]
   */
  public function getDeniedPermissions()
  {
    return $this->deniedPermissions;
  }
  /**
   * Lists all denied principals in the deny rule and indicates whether each
   * principal matches the principal in the request, either directly or through
   * membership in a principal set. Each key identifies a denied principal in
   * the rule, and each value indicates whether the denied principal matches the
   * principal in the request.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching[] $deniedPrincipals
   */
  public function setDeniedPrincipals($deniedPrincipals)
  {
    $this->deniedPrincipals = $deniedPrincipals;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching[]
   */
  public function getDeniedPrincipals()
  {
    return $this->deniedPrincipals;
  }
  /**
   * Required. Indicates whether _this rule_ denies the specified permission to
   * the specified principal for the specified resource. This field does _not_
   * indicate whether the principal is actually denied on the permission for the
   * resource. There might be another rule that overrides this rule. To
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
   * Lists all exception permissions in the deny rule and indicates whether each
   * permission matches the permission in the request. Each key identifies a
   * exception permission in the rule, and each value indicates whether the
   * exception permission matches the permission in the request.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching[] $exceptionPermissions
   */
  public function setExceptionPermissions($exceptionPermissions)
  {
    $this->exceptionPermissions = $exceptionPermissions;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedPermissionMatching[]
   */
  public function getExceptionPermissions()
  {
    return $this->exceptionPermissions;
  }
  /**
   * Lists all exception principals in the deny rule and indicates whether each
   * principal matches the principal in the request, either directly or through
   * membership in a principal set. Each key identifies a exception principal in
   * the rule, and each value indicates whether the exception principal matches
   * the principal in the request.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching[] $exceptionPrincipals
   */
  public function setExceptionPrincipals($exceptionPrincipals)
  {
    $this->exceptionPrincipals = $exceptionPrincipals;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanationAnnotatedDenyPrincipalMatching[]
   */
  public function getExceptionPrincipals()
  {
    return $this->exceptionPrincipals;
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
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3DenyRuleExplanation');
