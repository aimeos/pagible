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

class GoogleCloudDialogflowCxV3DataStoreConnectionSignalsSafetySignals extends \Google\Model
{
  public const BANNED_PHRASE_MATCH_BANNED_PHRASE_MATCH_UNSPECIFIED = 'BANNED_PHRASE_MATCH_UNSPECIFIED';
  public const BANNED_PHRASE_MATCH_BANNED_PHRASE_MATCH_NONE = 'BANNED_PHRASE_MATCH_NONE';
  public const BANNED_PHRASE_MATCH_BANNED_PHRASE_MATCH_QUERY = 'BANNED_PHRASE_MATCH_QUERY';
  public const BANNED_PHRASE_MATCH_BANNED_PHRASE_MATCH_RESPONSE = 'BANNED_PHRASE_MATCH_RESPONSE';
  public const DECISION_SAFETY_DECISION_UNSPECIFIED = 'SAFETY_DECISION_UNSPECIFIED';
  public const DECISION_ACCEPTED_BY_SAFETY_CHECK = 'ACCEPTED_BY_SAFETY_CHECK';
  public const DECISION_REJECTED_BY_SAFETY_CHECK = 'REJECTED_BY_SAFETY_CHECK';
  /**
   * @var string
   */
  public $bannedPhraseMatch;
  /**
   * @var string
   */
  public $decision;
  /**
   * @var string
   */
  public $matchedBannedPhrase;

  /**
   * @param self::BANNED_PHRASE_MATCH_* $bannedPhraseMatch
   */
  public function setBannedPhraseMatch($bannedPhraseMatch)
  {
    $this->bannedPhraseMatch = $bannedPhraseMatch;
  }
  /**
   * @return self::BANNED_PHRASE_MATCH_*
   */
  public function getBannedPhraseMatch()
  {
    return $this->bannedPhraseMatch;
  }
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
   * @param string $matchedBannedPhrase
   */
  public function setMatchedBannedPhrase($matchedBannedPhrase)
  {
    $this->matchedBannedPhrase = $matchedBannedPhrase;
  }
  /**
   * @return string
   */
  public function getMatchedBannedPhrase()
  {
    return $this->matchedBannedPhrase;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3DataStoreConnectionSignalsSafetySignals::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3DataStoreConnectionSignalsSafetySignals');
