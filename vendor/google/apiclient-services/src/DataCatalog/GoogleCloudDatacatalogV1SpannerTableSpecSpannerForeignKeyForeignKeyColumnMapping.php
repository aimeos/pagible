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

namespace Google\Service\DataCatalog;

class GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping extends \Google\Model
{
  /**
   * Output only. The column in the current table that is part of the foreign
   * key.
   *
   * @var string
   */
  public $column;
  /**
   * Output only. The column in the referenced table that is part of the foreign
   * key.
   *
   * @var string
   */
  public $referenceColumn;

  /**
   * Output only. The column in the current table that is part of the foreign
   * key.
   *
   * @param string $column
   */
  public function setColumn($column)
  {
    $this->column = $column;
  }
  /**
   * @return string
   */
  public function getColumn()
  {
    return $this->column;
  }
  /**
   * Output only. The column in the referenced table that is part of the foreign
   * key.
   *
   * @param string $referenceColumn
   */
  public function setReferenceColumn($referenceColumn)
  {
    $this->referenceColumn = $referenceColumn;
  }
  /**
   * @return string
   */
  public function getReferenceColumn()
  {
    return $this->referenceColumn;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping::class, 'Google_Service_DataCatalog_GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping');
