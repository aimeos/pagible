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

class GoogleCloudDialogflowV2StreamingRecognitionResult extends \Google\Collection
{
  public const MESSAGE_TYPE_MESSAGE_TYPE_UNSPECIFIED = 'MESSAGE_TYPE_UNSPECIFIED';
  public const MESSAGE_TYPE_TRANSCRIPT = 'TRANSCRIPT';
  public const MESSAGE_TYPE_END_OF_SINGLE_UTTERANCE = 'END_OF_SINGLE_UTTERANCE';
  protected $collection_key = 'speechWordInfo';
  /**
   * @var float
   */
  public $confidence;
  /**
   * @var bool
   */
  public $isFinal;
  /**
   * @var string
   */
  public $languageCode;
  /**
   * @var string
   */
  public $messageType;
  /**
   * @var string
   */
  public $speechEndOffset;
  protected $speechWordInfoType = GoogleCloudDialogflowV2SpeechWordInfo::class;
  protected $speechWordInfoDataType = 'array';
  /**
   * @var string
   */
  public $transcript;

  /**
   * @param float $confidence
   */
  public function setConfidence($confidence)
  {
    $this->confidence = $confidence;
  }
  /**
   * @return float
   */
  public function getConfidence()
  {
    return $this->confidence;
  }
  /**
   * @param bool $isFinal
   */
  public function setIsFinal($isFinal)
  {
    $this->isFinal = $isFinal;
  }
  /**
   * @return bool
   */
  public function getIsFinal()
  {
    return $this->isFinal;
  }
  /**
   * @param string $languageCode
   */
  public function setLanguageCode($languageCode)
  {
    $this->languageCode = $languageCode;
  }
  /**
   * @return string
   */
  public function getLanguageCode()
  {
    return $this->languageCode;
  }
  /**
   * @param self::MESSAGE_TYPE_* $messageType
   */
  public function setMessageType($messageType)
  {
    $this->messageType = $messageType;
  }
  /**
   * @return self::MESSAGE_TYPE_*
   */
  public function getMessageType()
  {
    return $this->messageType;
  }
  /**
   * @param string $speechEndOffset
   */
  public function setSpeechEndOffset($speechEndOffset)
  {
    $this->speechEndOffset = $speechEndOffset;
  }
  /**
   * @return string
   */
  public function getSpeechEndOffset()
  {
    return $this->speechEndOffset;
  }
  /**
   * @param GoogleCloudDialogflowV2SpeechWordInfo[] $speechWordInfo
   */
  public function setSpeechWordInfo($speechWordInfo)
  {
    $this->speechWordInfo = $speechWordInfo;
  }
  /**
   * @return GoogleCloudDialogflowV2SpeechWordInfo[]
   */
  public function getSpeechWordInfo()
  {
    return $this->speechWordInfo;
  }
  /**
   * @param string $transcript
   */
  public function setTranscript($transcript)
  {
    $this->transcript = $transcript;
  }
  /**
   * @return string
   */
  public function getTranscript()
  {
    return $this->transcript;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2StreamingRecognitionResult::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2StreamingRecognitionResult');
