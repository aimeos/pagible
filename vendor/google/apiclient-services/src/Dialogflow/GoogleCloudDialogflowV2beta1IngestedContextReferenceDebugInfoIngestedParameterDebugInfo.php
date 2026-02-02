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

class GoogleCloudDialogflowV2beta1IngestedContextReferenceDebugInfoIngestedParameterDebugInfo extends \Google\Model
{
  public const INGESTION_STATUS_INGESTION_STATUS_UNSPECIFIED = 'INGESTION_STATUS_UNSPECIFIED';
  public const INGESTION_STATUS_INGESTION_STATUS_SUCCEEDED = 'INGESTION_STATUS_SUCCEEDED';
  public const INGESTION_STATUS_INGESTION_STATUS_CONTEXT_NOT_AVAILABLE = 'INGESTION_STATUS_CONTEXT_NOT_AVAILABLE';
  public const INGESTION_STATUS_INGESTION_STATUS_PARSE_FAILED = 'INGESTION_STATUS_PARSE_FAILED';
  public const INGESTION_STATUS_INGESTION_STATUS_INVALID_ENTRY = 'INGESTION_STATUS_INVALID_ENTRY';
  public const INGESTION_STATUS_INGESTION_STATUS_INVALID_FORMAT = 'INGESTION_STATUS_INVALID_FORMAT';
  public const INGESTION_STATUS_INGESTION_STATUS_LANGUAGE_MISMATCH = 'INGESTION_STATUS_LANGUAGE_MISMATCH';
  /**
   * @var string
   */
  public $ingestionStatus;
  /**
   * @var string
   */
  public $parameter;

  /**
   * @param self::INGESTION_STATUS_* $ingestionStatus
   */
  public function setIngestionStatus($ingestionStatus)
  {
    $this->ingestionStatus = $ingestionStatus;
  }
  /**
   * @return self::INGESTION_STATUS_*
   */
  public function getIngestionStatus()
  {
    return $this->ingestionStatus;
  }
  /**
   * @param string $parameter
   */
  public function setParameter($parameter)
  {
    $this->parameter = $parameter;
  }
  /**
   * @return string
   */
  public function getParameter()
  {
    return $this->parameter;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2beta1IngestedContextReferenceDebugInfoIngestedParameterDebugInfo::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1IngestedContextReferenceDebugInfoIngestedParameterDebugInfo');
