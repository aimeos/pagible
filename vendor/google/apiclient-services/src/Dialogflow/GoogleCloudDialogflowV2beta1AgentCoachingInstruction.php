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

namespace Google\Service\Dialogflow;

class GoogleCloudDialogflowV2beta1AgentCoachingInstruction extends \Google\Model
{
  public const TRIGGERING_EVENT_TRIGGER_EVENT_UNSPECIFIED = 'TRIGGER_EVENT_UNSPECIFIED';
  public const TRIGGERING_EVENT_END_OF_UTTERANCE = 'END_OF_UTTERANCE';
  public const TRIGGERING_EVENT_MANUAL_CALL = 'MANUAL_CALL';
  public const TRIGGERING_EVENT_CUSTOMER_MESSAGE = 'CUSTOMER_MESSAGE';
  public const TRIGGERING_EVENT_AGENT_MESSAGE = 'AGENT_MESSAGE';
  public const TRIGGERING_EVENT_TOOL_CALL_COMPLETION = 'TOOL_CALL_COMPLETION';
  /**
   * @var string
   */
  public $agentAction;
  /**
   * @var string
   */
  public $condition;
  /**
   * @var string
   */
  public $displayDetails;
  /**
   * @var string
   */
  public $displayName;
  protected $duplicateCheckResultType = GoogleCloudDialogflowV2beta1AgentCoachingInstructionDuplicateCheckResult::class;
  protected $duplicateCheckResultDataType = '';
  /**
   * @var string
   */
  public $systemAction;
  /**
   * @var string
   */
  public $triggeringEvent;

  /**
   * @param string $agentAction
   */
  public function setAgentAction($agentAction)
  {
    $this->agentAction = $agentAction;
  }
  /**
   * @return string
   */
  public function getAgentAction()
  {
    return $this->agentAction;
  }
  /**
   * @param string $condition
   */
  public function setCondition($condition)
  {
    $this->condition = $condition;
  }
  /**
   * @return string
   */
  public function getCondition()
  {
    return $this->condition;
  }
  /**
   * @param string $displayDetails
   */
  public function setDisplayDetails($displayDetails)
  {
    $this->displayDetails = $displayDetails;
  }
  /**
   * @return string
   */
  public function getDisplayDetails()
  {
    return $this->displayDetails;
  }
  /**
   * @param string $displayName
   */
  public function setDisplayName($displayName)
  {
    $this->displayName = $displayName;
  }
  /**
   * @return string
   */
  public function getDisplayName()
  {
    return $this->displayName;
  }
  /**
   * @param GoogleCloudDialogflowV2beta1AgentCoachingInstructionDuplicateCheckResult $duplicateCheckResult
   */
  public function setDuplicateCheckResult(GoogleCloudDialogflowV2beta1AgentCoachingInstructionDuplicateCheckResult $duplicateCheckResult)
  {
    $this->duplicateCheckResult = $duplicateCheckResult;
  }
  /**
   * @return GoogleCloudDialogflowV2beta1AgentCoachingInstructionDuplicateCheckResult
   */
  public function getDuplicateCheckResult()
  {
    return $this->duplicateCheckResult;
  }
  /**
   * @param string $systemAction
   */
  public function setSystemAction($systemAction)
  {
    $this->systemAction = $systemAction;
  }
  /**
   * @return string
   */
  public function getSystemAction()
  {
    return $this->systemAction;
  }
  /**
   * @param self::TRIGGERING_EVENT_* $triggeringEvent
   */
  public function setTriggeringEvent($triggeringEvent)
  {
    $this->triggeringEvent = $triggeringEvent;
  }
  /**
   * @return self::TRIGGERING_EVENT_*
   */
  public function getTriggeringEvent()
  {
    return $this->triggeringEvent;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2beta1AgentCoachingInstruction::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1AgentCoachingInstruction');
