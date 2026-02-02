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

class GoogleCloudDialogflowCxV3DataStoreConnectionSignalsGroundingSignals extends \Google\Model
{
  public const DECISION_GROUNDING_DECISION_UNSPECIFIED = 'GROUNDING_DECISION_UNSPECIFIED';
  public const DECISION_ACCEPTED_BY_GROUNDING = 'ACCEPTED_BY_GROUNDING';
  public const DECISION_REJECTED_BY_GROUNDING = 'REJECTED_BY_GROUNDING';
  public const SCORE_GROUNDING_SCORE_BUCKET_UNSPECIFIED = 'GROUNDING_SCORE_BUCKET_UNSPECIFIED';
  public const SCORE_VERY_LOW = 'VERY_LOW';
  public const SCORE_LOW = 'LOW';
  public const SCORE_MEDIUM = 'MEDIUM';
  public const SCORE_HIGH = 'HIGH';
  public const SCORE_VERY_HIGH = 'VERY_HIGH';
  /**
   * @var string
   */
  public $decision;
  /**
   * @var string
   */
  public $score;

  /**
   * @param self::DECISION_* $decision
   */
  public function setDecision($decision)
  {
    $this->decision = $decision;
  }
  /**
   * @return self::DECISION_*
   */
  public function getDecision()
  {
    return $this->decision;
  }
  /**
   * @param self::SCORE_* $score
   */
  public function setScore($score)
  {
    $this->score = $score;
  }
  /**
   * @return self::SCORE_*
   */
  public function getScore()
  {
    return $this->score;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3DataStoreConnectionSignalsGroundingSignals::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3DataStoreConnectionSignalsGroundingSignals');
