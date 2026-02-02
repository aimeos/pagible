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

class GoogleCloudDialogflowV2IngestedContextReferenceDebugInfo extends \Google\Collection
{
  protected $collection_key = 'ingestedParametersDebugInfo';
  /**
   * @var bool
   */
  public $contextReferenceRetrieved;
  protected $ingestedParametersDebugInfoType = GoogleCloudDialogflowV2IngestedContextReferenceDebugInfoIngestedParameterDebugInfo::class;
  protected $ingestedParametersDebugInfoDataType = 'array';
  /**
   * @var bool
   */
  public $projectNotAllowlisted;

  /**
   * @param bool $contextReferenceRetrieved
   */
  public function setContextReferenceRetrieved($contextReferenceRetrieved)
  {
    $this->contextReferenceRetrieved = $contextReferenceRetrieved;
  }
  /**
   * @return bool
   */
  public function getContextReferenceRetrieved()
  {
    return $this->contextReferenceRetrieved;
  }
  /**
   * @param GoogleCloudDialogflowV2IngestedContextReferenceDebugInfoIngestedParameterDebugInfo[] $ingestedParametersDebugInfo
   */
  public function setIngestedParametersDebugInfo($ingestedParametersDebugInfo)
  {
    $this->ingestedParametersDebugInfo = $ingestedParametersDebugInfo;
  }
  /**
   * @return GoogleCloudDialogflowV2IngestedContextReferenceDebugInfoIngestedParameterDebugInfo[]
   */
  public function getIngestedParametersDebugInfo()
  {
    return $this->ingestedParametersDebugInfo;
  }
  /**
   * @param bool $projectNotAllowlisted
   */
  public function setProjectNotAllowlisted($projectNotAllowlisted)
  {
    $this->projectNotAllowlisted = $projectNotAllowlisted;
  }
  /**
   * @return bool
   */
  public function getProjectNotAllowlisted()
  {
    return $this->projectNotAllowlisted;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2IngestedContextReferenceDebugInfo::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2IngestedContextReferenceDebugInfo');
