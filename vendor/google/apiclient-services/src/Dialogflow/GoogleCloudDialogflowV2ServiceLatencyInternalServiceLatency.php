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

class GoogleCloudDialogflowV2ServiceLatencyInternalServiceLatency extends \Google\Model
{
  /**
   * @var string
   */
  public $completeTime;
  /**
   * @var float
   */
  public $latencyMs;
  /**
   * @var string
   */
  public $startTime;
  /**
   * @var string
   */
  public $step;

  /**
   * @param string $completeTime
   */
  public function setCompleteTime($completeTime)
  {
    $this->completeTime = $completeTime;
  }
  /**
   * @return string
   */
  public function getCompleteTime()
  {
    return $this->completeTime;
  }
  /**
   * @param float $latencyMs
   */
  public function setLatencyMs($latencyMs)
  {
    $this->latencyMs = $latencyMs;
  }
  /**
   * @return float
   */
  public function getLatencyMs()
  {
    return $this->latencyMs;
  }
  /**
   * @param string $startTime
   */
  public function setStartTime($startTime)
  {
    $this->startTime = $startTime;
  }
  /**
   * @return string
   */
  public function getStartTime()
  {
    return $this->startTime;
  }
  /**
   * @param string $step
   */
  public function setStep($step)
  {
    $this->step = $step;
  }
  /**
   * @return string
   */
  public function getStep()
  {
    return $this->step;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2ServiceLatencyInternalServiceLatency::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2ServiceLatencyInternalServiceLatency');
