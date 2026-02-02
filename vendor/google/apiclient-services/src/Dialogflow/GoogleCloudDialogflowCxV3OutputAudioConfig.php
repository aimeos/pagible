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

class GoogleCloudDialogflowCxV3OutputAudioConfig extends \Google\Model
{
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_UNSPECIFIED = 'OUTPUT_AUDIO_ENCODING_UNSPECIFIED';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_LINEAR_16 = 'OUTPUT_AUDIO_ENCODING_LINEAR_16';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_MP3 = 'OUTPUT_AUDIO_ENCODING_MP3';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_MP3_64_KBPS = 'OUTPUT_AUDIO_ENCODING_MP3_64_KBPS';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_OGG_OPUS = 'OUTPUT_AUDIO_ENCODING_OGG_OPUS';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_MULAW = 'OUTPUT_AUDIO_ENCODING_MULAW';
  public const AUDIO_ENCODING_OUTPUT_AUDIO_ENCODING_ALAW = 'OUTPUT_AUDIO_ENCODING_ALAW';
  /**
   * @var string
   */
  public $audioEncoding;
  /**
   * @var int
   */
  public $sampleRateHertz;
  protected $synthesizeSpeechConfigType = GoogleCloudDialogflowCxV3SynthesizeSpeechConfig::class;
  protected $synthesizeSpeechConfigDataType = '';

  /**
   * @param self::AUDIO_ENCODING_* $audioEncoding
   */
  public function setAudioEncoding($audioEncoding)
  {
    $this->audioEncoding = $audioEncoding;
  }
  /**
   * @return self::AUDIO_ENCODING_*
   */
  public function getAudioEncoding()
  {
    return $this->audioEncoding;
  }
  /**
   * @param int $sampleRateHertz
   */
  public function setSampleRateHertz($sampleRateHertz)
  {
    $this->sampleRateHertz = $sampleRateHertz;
  }
  /**
   * @return int
   */
  public function getSampleRateHertz()
  {
    return $this->sampleRateHertz;
  }
  /**
   * @param GoogleCloudDialogflowCxV3SynthesizeSpeechConfig $synthesizeSpeechConfig
   */
  public function setSynthesizeSpeechConfig(GoogleCloudDialogflowCxV3SynthesizeSpeechConfig $synthesizeSpeechConfig)
  {
    $this->synthesizeSpeechConfig = $synthesizeSpeechConfig;
  }
  /**
   * @return GoogleCloudDialogflowCxV3SynthesizeSpeechConfig
   */
  public function getSynthesizeSpeechConfig()
  {
    return $this->synthesizeSpeechConfig;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3OutputAudioConfig::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3OutputAudioConfig');
