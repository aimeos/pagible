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

class GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState extends \Google\Collection
{
  protected $collection_key = 'errors';
  /**
   * End position of an expression in the condition, by character, end included,
   * for example: the end position of the first part of `a==b || c==d` would be
   * 4.
   *
   * @var int
   */
  public $end;
  protected $errorsType = GoogleRpcStatus::class;
  protected $errorsDataType = 'array';
  /**
   * Start position of an expression in the condition, by character.
   *
   * @var int
   */
  public $start;
  /**
   * Value of this expression.
   *
   * @var array
   */
  public $value;

  /**
   * End position of an expression in the condition, by character, end included,
   * for example: the end position of the first part of `a==b || c==d` would be
   * 4.
   *
   * @param int $end
   */
  public function setEnd($end)
  {
    $this->end = $end;
  }
  /**
   * @return int
   */
  public function getEnd()
  {
    return $this->end;
  }
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
   * Start position of an expression in the condition, by character.
   *
   * @param int $start
   */
  public function setStart($start)
  {
    $this->start = $start;
  }
  /**
   * @return int
   */
  public function getStart()
  {
    return $this->start;
  }
  /**
   * Value of this expression.
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
class_alias(GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ConditionExplanationEvaluationState');
