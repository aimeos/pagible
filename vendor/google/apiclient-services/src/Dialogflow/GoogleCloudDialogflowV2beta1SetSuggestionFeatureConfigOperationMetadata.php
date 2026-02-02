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

class GoogleCloudDialogflowV2beta1SetSuggestionFeatureConfigOperationMetadata extends \Google\Model
{
  public const PARTICIPANT_ROLE_ROLE_UNSPECIFIED = 'ROLE_UNSPECIFIED';
  public const PARTICIPANT_ROLE_HUMAN_AGENT = 'HUMAN_AGENT';
  public const PARTICIPANT_ROLE_AUTOMATED_AGENT = 'AUTOMATED_AGENT';
  public const PARTICIPANT_ROLE_END_USER = 'END_USER';
  public const SUGGESTION_FEATURE_TYPE_TYPE_UNSPECIFIED = 'TYPE_UNSPECIFIED';
  public const SUGGESTION_FEATURE_TYPE_ARTICLE_SUGGESTION = 'ARTICLE_SUGGESTION';
  public const SUGGESTION_FEATURE_TYPE_FAQ = 'FAQ';
  public const SUGGESTION_FEATURE_TYPE_SMART_REPLY = 'SMART_REPLY';
  public const SUGGESTION_FEATURE_TYPE_DIALOGFLOW_ASSIST = 'DIALOGFLOW_ASSIST';
  public const SUGGESTION_FEATURE_TYPE_CONVERSATION_SUMMARIZATION = 'CONVERSATION_SUMMARIZATION';
  public const SUGGESTION_FEATURE_TYPE_KNOWLEDGE_SEARCH = 'KNOWLEDGE_SEARCH';
  public const SUGGESTION_FEATURE_TYPE_KNOWLEDGE_ASSIST = 'KNOWLEDGE_ASSIST';
  /**
   * @var string
   */
  public $conversationProfile;
  /**
   * @var string
   */
  public $createTime;
  /**
   * @var string
   */
  public $participantRole;
  /**
   * @var string
   */
  public $suggestionFeatureType;

  /**
   * @param string $conversationProfile
   */
  public function setConversationProfile($conversationProfile)
  {
    $this->conversationProfile = $conversationProfile;
  }
  /**
   * @return string
   */
  public function getConversationProfile()
  {
    return $this->conversationProfile;
  }
  /**
   * @param string $createTime
   */
  public function setCreateTime($createTime)
  {
    $this->createTime = $createTime;
  }
  /**
   * @return string
   */
  public function getCreateTime()
  {
    return $this->createTime;
  }
  /**
   * @param self::PARTICIPANT_ROLE_* $participantRole
   */
  public function setParticipantRole($participantRole)
  {
    $this->participantRole = $participantRole;
  }
  /**
   * @return self::PARTICIPANT_ROLE_*
   */
  public function getParticipantRole()
  {
    return $this->participantRole;
  }
  /**
   * @param self::SUGGESTION_FEATURE_TYPE_* $suggestionFeatureType
   */
  public function setSuggestionFeatureType($suggestionFeatureType)
  {
    $this->suggestionFeatureType = $suggestionFeatureType;
  }
  /**
   * @return self::SUGGESTION_FEATURE_TYPE_*
   */
  public function getSuggestionFeatureType()
  {
    return $this->suggestionFeatureType;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2beta1SetSuggestionFeatureConfigOperationMetadata::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1SetSuggestionFeatureConfigOperationMetadata');
