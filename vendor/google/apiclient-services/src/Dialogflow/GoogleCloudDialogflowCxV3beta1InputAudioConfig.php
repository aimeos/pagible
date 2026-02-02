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

class GoogleCloudDialogflowCxV3beta1InputAudioConfig extends \Google\Collection
{
  public const AUDIO_ENCODING_AUDIO_ENCODING_UNSPECIFIED = 'AUDIO_ENCODING_UNSPECIFIED';
  public const AUDIO_ENCODING_AUDIO_ENCODING_LINEAR_16 = 'AUDIO_ENCODING_LINEAR_16';
  public const AUDIO_ENCODING_AUDIO_ENCODING_FLAC = 'AUDIO_ENCODING_FLAC';
  public const AUDIO_ENCODING_AUDIO_ENCODING_MULAW = 'AUDIO_ENCODING_MULAW';
  public const AUDIO_ENCODING_AUDIO_ENCODING_AMR = 'AUDIO_ENCODING_AMR';
  public const AUDIO_ENCODING_AUDIO_ENCODING_AMR_WB = 'AUDIO_ENCODING_AMR_WB';
  public const AUDIO_ENCODING_AUDIO_ENCODING_OGG_OPUS = 'AUDIO_ENCODING_OGG_OPUS';
  public const AUDIO_ENCODING_AUDIO_ENCODING_SPEEX_WITH_HEADER_BYTE = 'AUDIO_ENCODING_SPEEX_WITH_HEADER_BYTE';
  public const AUDIO_ENCODING_AUDIO_ENCODING_ALAW = 'AUDIO_ENCODING_ALAW';
  public const MODEL_VARIANT_SPEECH_MODEL_VARIANT_UNSPECIFIED = 'SPEECH_MODEL_VARIANT_UNSPECIFIED';
  public const MODEL_VARIANT_USE_BEST_AVAILABLE = 'USE_BEST_AVAILABLE';
  public const MODEL_VARIANT_USE_STANDARD = 'USE_STANDARD';
  public const MODEL_VARIANT_USE_ENHANCED = 'USE_ENHANCED';
  protected $collection_key = 'phraseHints';
  /**
   * @var string
   */
  public $audioEncoding;
  protected $bargeInConfigType = GoogleCloudDialogflowCxV3beta1BargeInConfig::class;
  protected $bargeInConfigDataType = '';
  /**
   * @var bool
   */
  public $enableWordInfo;
  /**
   * @var string
   */
  public $model;
  /**
   * @var string
   */
  public $modelVariant;
  /**
   * @var bool
   */
  public $optOutConformerModelMigration;
  /**
   * @var string[]
   */
  public $phraseHints;
  /**
   * @var int
   */
  public $sampleRateHertz;
  /**
   * @var bool
   */
  public $singleUtterance;

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
   * @param GoogleCloudDialogflowCxV3beta1BargeInConfig $bargeInConfig
   */
  public function setBargeInConfig(GoogleCloudDialogflowCxV3beta1BargeInConfig $bargeInConfig)
  {
    $this->bargeInConfig = $bargeInConfig;
  }
  /**
   * @return GoogleCloudDialogflowCxV3beta1BargeInConfig
   */
  public function getBargeInConfig()
  {
    return $this->bargeInConfig;
  }
  /**
   * @param bool $enableWordInfo
   */
  public function setEnableWordInfo($enableWordInfo)
  {
    $this->enableWordInfo = $enableWordInfo;
  }
  /**
   * @return bool
   */
  public function getEnableWordInfo()
  {
    return $this->enableWordInfo;
  }
  /**
   * @param string $model
   */
  public function setModel($model)
  {
    $this->model = $model;
  }
  /**
   * @return string
   */
  public function getModel()
  {
    return $this->model;
  }
  /**
   * @param self::MODEL_VARIANT_* $modelVariant
   */
  public function setModelVariant($modelVariant)
  {
    $this->modelVariant = $modelVariant;
  }
  /**
   * @return self::MODEL_VARIANT_*
   */
  public function getModelVariant()
  {
    return $this->modelVariant;
  }
  /**
   * @param bool $optOutConformerModelMigration
   */
  public function setOptOutConformerModelMigration($optOutConformerModelMigration)
  {
    $this->optOutConformerModelMigration = $optOutConformerModelMigration;
  }
  /**
   * @return bool
   */
  public function getOptOutConformerModelMigration()
  {
    return $this->optOutConformerModelMigration;
  }
  /**
   * @param string[] $phraseHints
   */
  public function setPhraseHints($phraseHints)
  {
    $this->phraseHints = $phraseHints;
  }
  /**
   * @return string[]
   */
  public function getPhraseHints()
  {
    return $this->phraseHints;
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
   * @param bool $singleUtterance
   */
  public function setSingleUtterance($singleUtterance)
  {
    $this->singleUtterance = $singleUtterance;
  }
  /**
   * @return bool
   */
  public function getSingleUtterance()
  {
    return $this->singleUtterance;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3beta1InputAudioConfig::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3beta1InputAudioConfig');
