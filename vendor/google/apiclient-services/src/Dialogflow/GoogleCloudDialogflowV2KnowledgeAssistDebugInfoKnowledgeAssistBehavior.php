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

class GoogleCloudDialogflowV2KnowledgeAssistDebugInfoKnowledgeAssistBehavior extends \Google\Model
{
  /**
   * @var bool
   */
  public $answerGenerationRewriterOn;
  /**
   * @var int
   */
  public $appendedSearchContextCount;
  /**
   * @var bool
   */
  public $conversationTranscriptHasMixedLanguages;
  /**
   * @var bool
   */
  public $disableSyncDelivery;
  /**
   * @var bool
   */
  public $endUserMetadataIncluded;
  /**
   * @var bool
   */
  public $invalidItemsQuerySuggestionSkipped;
  /**
   * @var bool
   */
  public $multipleQueriesGenerated;
  /**
   * @var bool
   */
  public $previousQueriesIncluded;
  /**
   * @var bool
   */
  public $primaryQueryRedactedAndReplaced;
  /**
   * @var bool
   */
  public $queryContainedSearchContext;
  /**
   * @var bool
   */
  public $queryGenerationAgentLanguageMismatch;
  /**
   * @var bool
   */
  public $queryGenerationEndUserLanguageMismatch;
  /**
   * @var bool
   */
  public $returnQueryOnly;
  /**
   * @var bool
   */
  public $thirdPartyConnectorAllowed;
  /**
   * @var bool
   */
  public $useCustomSafetyFilterLevel;
  /**
   * @var bool
   */
  public $usePubsubDelivery;
  /**
   * @var bool
   */
  public $useTranslatedMessage;

  /**
   * @param bool $answerGenerationRewriterOn
   */
  public function setAnswerGenerationRewriterOn($answerGenerationRewriterOn)
  {
    $this->answerGenerationRewriterOn = $answerGenerationRewriterOn;
  }
  /**
   * @return bool
   */
  public function getAnswerGenerationRewriterOn()
  {
    return $this->answerGenerationRewriterOn;
  }
  /**
   * @param int $appendedSearchContextCount
   */
  public function setAppendedSearchContextCount($appendedSearchContextCount)
  {
    $this->appendedSearchContextCount = $appendedSearchContextCount;
  }
  /**
   * @return int
   */
  public function getAppendedSearchContextCount()
  {
    return $this->appendedSearchContextCount;
  }
  /**
   * @param bool $conversationTranscriptHasMixedLanguages
   */
  public function setConversationTranscriptHasMixedLanguages($conversationTranscriptHasMixedLanguages)
  {
    $this->conversationTranscriptHasMixedLanguages = $conversationTranscriptHasMixedLanguages;
  }
  /**
   * @return bool
   */
  public function getConversationTranscriptHasMixedLanguages()
  {
    return $this->conversationTranscriptHasMixedLanguages;
  }
  /**
   * @param bool $disableSyncDelivery
   */
  public function setDisableSyncDelivery($disableSyncDelivery)
  {
    $this->disableSyncDelivery = $disableSyncDelivery;
  }
  /**
   * @return bool
   */
  public function getDisableSyncDelivery()
  {
    return $this->disableSyncDelivery;
  }
  /**
   * @param bool $endUserMetadataIncluded
   */
  public function setEndUserMetadataIncluded($endUserMetadataIncluded)
  {
    $this->endUserMetadataIncluded = $endUserMetadataIncluded;
  }
  /**
   * @return bool
   */
  public function getEndUserMetadataIncluded()
  {
    return $this->endUserMetadataIncluded;
  }
  /**
   * @param bool $invalidItemsQuerySuggestionSkipped
   */
  public function setInvalidItemsQuerySuggestionSkipped($invalidItemsQuerySuggestionSkipped)
  {
    $this->invalidItemsQuerySuggestionSkipped = $invalidItemsQuerySuggestionSkipped;
  }
  /**
   * @return bool
   */
  public function getInvalidItemsQuerySuggestionSkipped()
  {
    return $this->invalidItemsQuerySuggestionSkipped;
  }
  /**
   * @param bool $multipleQueriesGenerated
   */
  public function setMultipleQueriesGenerated($multipleQueriesGenerated)
  {
    $this->multipleQueriesGenerated = $multipleQueriesGenerated;
  }
  /**
   * @return bool
   */
  public function getMultipleQueriesGenerated()
  {
    return $this->multipleQueriesGenerated;
  }
  /**
   * @param bool $previousQueriesIncluded
   */
  public function setPreviousQueriesIncluded($previousQueriesIncluded)
  {
    $this->previousQueriesIncluded = $previousQueriesIncluded;
  }
  /**
   * @return bool
   */
  public function getPreviousQueriesIncluded()
  {
    return $this->previousQueriesIncluded;
  }
  /**
   * @param bool $primaryQueryRedactedAndReplaced
   */
  public function setPrimaryQueryRedactedAndReplaced($primaryQueryRedactedAndReplaced)
  {
    $this->primaryQueryRedactedAndReplaced = $primaryQueryRedactedAndReplaced;
  }
  /**
   * @return bool
   */
  public function getPrimaryQueryRedactedAndReplaced()
  {
    return $this->primaryQueryRedactedAndReplaced;
  }
  /**
   * @param bool $queryContainedSearchContext
   */
  public function setQueryContainedSearchContext($queryContainedSearchContext)
  {
    $this->queryContainedSearchContext = $queryContainedSearchContext;
  }
  /**
   * @return bool
   */
  public function getQueryContainedSearchContext()
  {
    return $this->queryContainedSearchContext;
  }
  /**
   * @param bool $queryGenerationAgentLanguageMismatch
   */
  public function setQueryGenerationAgentLanguageMismatch($queryGenerationAgentLanguageMismatch)
  {
    $this->queryGenerationAgentLanguageMismatch = $queryGenerationAgentLanguageMismatch;
  }
  /**
   * @return bool
   */
  public function getQueryGenerationAgentLanguageMismatch()
  {
    return $this->queryGenerationAgentLanguageMismatch;
  }
  /**
   * @param bool $queryGenerationEndUserLanguageMismatch
   */
  public function setQueryGenerationEndUserLanguageMismatch($queryGenerationEndUserLanguageMismatch)
  {
    $this->queryGenerationEndUserLanguageMismatch = $queryGenerationEndUserLanguageMismatch;
  }
  /**
   * @return bool
   */
  public function getQueryGenerationEndUserLanguageMismatch()
  {
    return $this->queryGenerationEndUserLanguageMismatch;
  }
  /**
   * @param bool $returnQueryOnly
   */
  public function setReturnQueryOnly($returnQueryOnly)
  {
    $this->returnQueryOnly = $returnQueryOnly;
  }
  /**
   * @return bool
   */
  public function getReturnQueryOnly()
  {
    return $this->returnQueryOnly;
  }
  /**
   * @param bool $thirdPartyConnectorAllowed
   */
  public function setThirdPartyConnectorAllowed($thirdPartyConnectorAllowed)
  {
    $this->thirdPartyConnectorAllowed = $thirdPartyConnectorAllowed;
  }
  /**
   * @return bool
   */
  public function getThirdPartyConnectorAllowed()
  {
    return $this->thirdPartyConnectorAllowed;
  }
  /**
   * @param bool $useCustomSafetyFilterLevel
   */
  public function setUseCustomSafetyFilterLevel($useCustomSafetyFilterLevel)
  {
    $this->useCustomSafetyFilterLevel = $useCustomSafetyFilterLevel;
  }
  /**
   * @return bool
   */
  public function getUseCustomSafetyFilterLevel()
  {
    return $this->useCustomSafetyFilterLevel;
  }
  /**
   * @param bool $usePubsubDelivery
   */
  public function setUsePubsubDelivery($usePubsubDelivery)
  {
    $this->usePubsubDelivery = $usePubsubDelivery;
  }
  /**
   * @return bool
   */
  public function getUsePubsubDelivery()
  {
    return $this->usePubsubDelivery;
  }
  /**
   * @param bool $useTranslatedMessage
   */
  public function setUseTranslatedMessage($useTranslatedMessage)
  {
    $this->useTranslatedMessage = $useTranslatedMessage;
  }
  /**
   * @return bool
   */
  public function getUseTranslatedMessage()
  {
    return $this->useTranslatedMessage;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2KnowledgeAssistDebugInfoKnowledgeAssistBehavior::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2KnowledgeAssistDebugInfoKnowledgeAssistBehavior');
