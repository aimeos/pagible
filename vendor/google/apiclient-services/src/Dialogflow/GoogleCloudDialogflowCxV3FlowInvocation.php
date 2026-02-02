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

class GoogleCloudDialogflowCxV3FlowInvocation extends \Google\Model
{
  public const FLOW_STATE_OUTPUT_STATE_UNSPECIFIED = 'OUTPUT_STATE_UNSPECIFIED';
  public const FLOW_STATE_OUTPUT_STATE_OK = 'OUTPUT_STATE_OK';
  public const FLOW_STATE_OUTPUT_STATE_CANCELLED = 'OUTPUT_STATE_CANCELLED';
  public const FLOW_STATE_OUTPUT_STATE_FAILED = 'OUTPUT_STATE_FAILED';
  public const FLOW_STATE_OUTPUT_STATE_ESCALATED = 'OUTPUT_STATE_ESCALATED';
  public const FLOW_STATE_OUTPUT_STATE_PENDING = 'OUTPUT_STATE_PENDING';
  /**
   * @var string
   */
  public $displayName;
  /**
   * @var string
   */
  public $flow;
  /**
   * @var string
   */
  public $flowState;

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
   * @param string $flow
   */
  public function setFlow($flow)
  {
    $this->flow = $flow;
  }
  /**
   * @return string
   */
  public function getFlow()
  {
    return $this->flow;
  }
  /**
   * @param self::FLOW_STATE_* $flowState
   */
  public function setFlowState($flowState)
  {
    $this->flowState = $flowState;
  }
  /**
   * @return self::FLOW_STATE_*
   */
  public function getFlowState()
  {
    return $this->flowState;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3FlowInvocation::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3FlowInvocation');
