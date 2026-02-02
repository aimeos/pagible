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

class GoogleCloudDialogflowCxV3DataStoreConnection extends \Google\Model
{
  public const DATA_STORE_TYPE_DATA_STORE_TYPE_UNSPECIFIED = 'DATA_STORE_TYPE_UNSPECIFIED';
  public const DATA_STORE_TYPE_PUBLIC_WEB = 'PUBLIC_WEB';
  public const DATA_STORE_TYPE_UNSTRUCTURED = 'UNSTRUCTURED';
  public const DATA_STORE_TYPE_STRUCTURED = 'STRUCTURED';
  public const DOCUMENT_PROCESSING_MODE_DOCUMENT_PROCESSING_MODE_UNSPECIFIED = 'DOCUMENT_PROCESSING_MODE_UNSPECIFIED';
  public const DOCUMENT_PROCESSING_MODE_DOCUMENTS = 'DOCUMENTS';
  public const DOCUMENT_PROCESSING_MODE_CHUNKS = 'CHUNKS';
  /**
   * @var string
   */
  public $dataStore;
  /**
   * @var string
   */
  public $dataStoreType;
  /**
   * @var string
   */
  public $documentProcessingMode;

  /**
   * @param string $dataStore
   */
  public function setDataStore($dataStore)
  {
    $this->dataStore = $dataStore;
  }
  /**
   * @return string
   */
  public function getDataStore()
  {
    return $this->dataStore;
  }
  /**
   * @param self::DATA_STORE_TYPE_* $dataStoreType
   */
  public function setDataStoreType($dataStoreType)
  {
    $this->dataStoreType = $dataStoreType;
  }
  /**
   * @return self::DATA_STORE_TYPE_*
   */
  public function getDataStoreType()
  {
    return $this->dataStoreType;
  }
  /**
   * @param self::DOCUMENT_PROCESSING_MODE_* $documentProcessingMode
   */
  public function setDocumentProcessingMode($documentProcessingMode)
  {
    $this->documentProcessingMode = $documentProcessingMode;
  }
  /**
   * @return self::DOCUMENT_PROCESSING_MODE_*
   */
  public function getDocumentProcessingMode()
  {
    return $this->documentProcessingMode;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3DataStoreConnection::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3DataStoreConnection');
