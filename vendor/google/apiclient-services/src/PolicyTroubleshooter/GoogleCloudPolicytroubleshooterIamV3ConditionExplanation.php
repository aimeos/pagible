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

class GoogleCloudPolicytroubleshooterIamV3ConditionExplanation extends \Google\Collection
{
  protected $collection_key = 'evaluationStates';
  protected $errorsType = GoogleRpcStatus::class;
  protected $errorsDataType = 'array';
  protected $evaluationStatesType = GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState::class;
  protected $evaluationStatesDataType = 'array';
  /**
   * Value of the condition.
   *
   * @var array
   */
  public $value;

  /**
   * Any errors that prevented complete evaluation of the condition expression.
   *
   * @param GoogleRpcStatus[] $errors
   */
  public function setErrors($errors)
  {
    $this->errors = $errors;
  }
  /**
   * @return GoogleRpcStatus[]
   */
  public function getErrors()
  {
    return $this->errors;
  }
  /**
   * The value of each statement of the condition expression. The value can be
   * `true`, `false`, or `null`. The value is `null` if the statement can't be
   * evaluated.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState[] $evaluationStates
   */
  public function setEvaluationStates($evaluationStates)
  {
    $this->evaluationStates = $evaluationStates;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState[]
   */
  public function getEvaluationStates()
  {
    return $this->evaluationStates;
  }
  /**
   * Value of the condition.
   *
   * @param array $value
   */
  public function setValue($value)
  {
    $this->value = $value;
  }
  /**
   * @return array
   */
  public function getValue()
  {
    return $this->value;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ConditionExplanation::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ConditionExplanation');
