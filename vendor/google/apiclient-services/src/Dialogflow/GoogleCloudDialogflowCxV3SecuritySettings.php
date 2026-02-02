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

class GoogleCloudDialogflowCxV3SecuritySettings extends \Google\Collection
{
  public const REDACTION_SCOPE_REDACTION_SCOPE_UNSPECIFIED = 'REDACTION_SCOPE_UNSPECIFIED';
  public const REDACTION_SCOPE_REDACT_DISK_STORAGE = 'REDACT_DISK_STORAGE';
  public const REDACTION_STRATEGY_REDACTION_STRATEGY_UNSPECIFIED = 'REDACTION_STRATEGY_UNSPECIFIED';
  public const REDACTION_STRATEGY_REDACT_WITH_SERVICE = 'REDACT_WITH_SERVICE';
  public const RETENTION_STRATEGY_RETENTION_STRATEGY_UNSPECIFIED = 'RETENTION_STRATEGY_UNSPECIFIED';
  public const RETENTION_STRATEGY_REMOVE_AFTER_CONVERSATION = 'REMOVE_AFTER_CONVERSATION';
  protected $collection_key = 'purgeDataTypes';
  protected $audioExportSettingsType = GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings::class;
  protected $audioExportSettingsDataType = '';
  /**
   * @var string
   */
  public $deidentifyTemplate;
  /**
   * @var string
   */
  public $displayName;
  protected $insightsExportSettingsType = GoogleCloudDialogflowCxV3SecuritySettingsInsightsExportSettings::class;
  protected $insightsExportSettingsDataType = '';
  /**
   * @var string
   */
  public $inspectTemplate;
  /**
   * @var string
   */
  public $name;
  /**
   * @var string[]
   */
  public $purgeDataTypes;
  /**
   * @var string
   */
  public $redactionScope;
  /**
   * @var string
   */
  public $redactionStrategy;
  /**
   * @var string
   */
  public $retentionStrategy;
  /**
   * @var int
   */
  public $retentionWindowDays;

  /**
   * @param GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings $audioExportSettings
   */
  public function setAudioExportSettings(GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings $audioExportSettings)
  {
    $this->audioExportSettings = $audioExportSettings;
  }
  /**
   * @return GoogleCloudDialogflowCxV3SecuritySettingsAudioExportSettings
   */
  public function getAudioExportSettings()
  {
    return $this->audioExportSettings;
  }
  /**
   * @param string $deidentifyTemplate
   */
  public function setDeidentifyTemplate($deidentifyTemplate)
  {
    $this->deidentifyTemplate = $deidentifyTemplate;
  }
  /**
   * @return string
   */
  public function getDeidentifyTemplate()
  {
    return $this->deidentifyTemplate;
  }
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
   * @param GoogleCloudDialogflowCxV3SecuritySettingsInsightsExportSettings $insightsExportSettings
   */
  public function setInsightsExportSettings(GoogleCloudDialogflowCxV3SecuritySettingsInsightsExportSettings $insightsExportSettings)
  {
    $this->insightsExportSettings = $insightsExportSettings;
  }
  /**
   * @return GoogleCloudDialogflowCxV3SecuritySettingsInsightsExportSettings
   */
  public function getInsightsExportSettings()
  {
    return $this->insightsExportSettings;
  }
  /**
   * @param string $inspectTemplate
   */
  public function setInspectTemplate($inspectTemplate)
  {
    $this->inspectTemplate = $inspectTemplate;
  }
  /**
   * @return string
   */
  public function getInspectTemplate()
  {
    return $this->inspectTemplate;
  }
  /**
   * @param string $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }
  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }
  /**
   * @param string[] $purgeDataTypes
   */
  public function setPurgeDataTypes($purgeDataTypes)
  {
    $this->purgeDataTypes = $purgeDataTypes;
  }
  /**
   * @return string[]
   */
  public function getPurgeDataTypes()
  {
    return $this->purgeDataTypes;
  }
  /**
   * @param self::REDACTION_SCOPE_* $redactionScope
   */
  public function setRedactionScope($redactionScope)
  {
    $this->redactionScope = $redactionScope;
  }
  /**
   * @return self::REDACTION_SCOPE_*
   */
  public function getRedactionScope()
  {
    return $this->redactionScope;
  }
  /**
   * @param self::REDACTION_STRATEGY_* $redactionStrategy
   */
  public function setRedactionStrategy($redactionStrategy)
  {
    $this->redactionStrategy = $redactionStrategy;
  }
  /**
   * @return self::REDACTION_STRATEGY_*
   */
  public function getRedactionStrategy()
  {
    return $this->redactionStrategy;
  }
  /**
   * @param self::RETENTION_STRATEGY_* $retentionStrategy
   */
  public function setRetentionStrategy($retentionStrategy)
  {
    $this->retentionStrategy = $retentionStrategy;
  }
  /**
   * @return self::RETENTION_STRATEGY_*
   */
  public function getRetentionStrategy()
  {
    return $this->retentionStrategy;
  }
  /**
   * @param int $retentionWindowDays
   */
  public function setRetentionWindowDays($retentionWindowDays)
  {
    $this->retentionWindowDays = $retentionWindowDays;
  }
  /**
   * @return int
   */
  public function getRetentionWindowDays()
  {
    return $this->retentionWindowDays;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3SecuritySettings::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3SecuritySettings');
