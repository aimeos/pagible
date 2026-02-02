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

class GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings extends \Google\Model
{
  public const AUDIO_FORMAT_AUDIO_FORMAT_UNSPECIFIED = 'AUDIO_FORMAT_UNSPECIFIED';
  public const AUDIO_FORMAT_MULAW = 'MULAW';
  public const AUDIO_FORMAT_MP3 = 'MP3';
  public const AUDIO_FORMAT_OGG = 'OGG';
  /**
   * @var string
   */
  public $audioExportPattern;
  /**
   * @var string
   */
  public $audioFormat;
  /**
   * @var bool
   */
  public $enableAudioRedaction;
  /**
   * @var string
   */
  public $gcsBucket;
  /**
   * @var bool
   */
  public $storeTtsAudio;

  /**
   * @param string $audioExportPattern
   */
  public function setAudioExportPattern($audioExportPattern)
  {
    $this->audioExportPattern = $audioExportPattern;
  }
  /**
   * @return string
   */
  public function getAudioExportPattern()
  {
    return $this->audioExportPattern;
  }
  /**
   * @param self::AUDIO_FORMAT_* $audioFormat
   */
  public function setAudioFormat($audioFormat)
  {
    $this->audioFormat = $audioFormat;
  }
  /**
   * @return self::AUDIO_FORMAT_*
   */
  public function getAudioFormat()
  {
    return $this->audioFormat;
  }
  /**
   * @param bool $enableAudioRedaction
   */
  public function setEnableAudioRedaction($enableAudioRedaction)
  {
    $this->enableAudioRedaction = $enableAudioRedaction;
  }
  /**
   * @return bool
   */
  public function getEnableAudioRedaction()
  {
    return $this->enableAudioRedaction;
  }
  /**
   * @param string $gcsBucket
   */
  public function setGcsBucket($gcsBucket)
  {
    $this->gcsBucket = $gcsBucket;
  }
  /**
   * @return string
   */
  public function getGcsBucket()
  {
    return $this->gcsBucket;
  }
  /**
   * @param bool $storeTtsAudio
   */
  public function setStoreTtsAudio($storeTtsAudio)
  {
    $this->storeTtsAudio = $storeTtsAudio;
  }
  /**
   * @return bool
   */
  public function getStoreTtsAudio()
  {
    return $this->storeTtsAudio;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings');
