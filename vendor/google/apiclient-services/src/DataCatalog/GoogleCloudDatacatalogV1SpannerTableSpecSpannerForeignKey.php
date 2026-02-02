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

class GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey extends \Google\Collection
{
  protected $collection_key = 'columnMappings';
  protected $columnMappingsType = GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping::class;
  protected $columnMappingsDataType = 'array';
  /**
   * Output only. The table name this foreign key referenced to. Format: `projec
   * ts/{PROJECT_ID}/locations/{LOCATION}/entryGroups/{ENTRY_GROUP_ID}/entries/{
   * ENTRY_ID}`
   *
   * @var string
   */
  public $entry;
  /**
   * Output only. The constraint_name of the foreign key, for example,
   * FK_CustomerOrder.
   *
   * @var string
   */
  public $name;

  /**
   * Output only. The ordered list of column mappings for this foreign key.
   *
   * @param GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping[] $columnMappings
   */
  public function setColumnMappings($columnMappings)
  {
    $this->columnMappings = $columnMappings;
  }
  /**
   * @return GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKeyForeignKeyColumnMapping[]
   */
  public function getColumnMappings()
  {
    return $this->columnMappings;
  }
  /**
   * Output only. The table name this foreign key referenced to. Format: `projec
   * ts/{PROJECT_ID}/locations/{LOCATION}/entryGroups/{ENTRY_GROUP_ID}/entries/{
   * ENTRY_ID}`
   *
   * @param string $entry
   */
  public function setEntry($entry)
  {
    $this->entry = $entry;
  }
  /**
   * @return string
   */
  public function getEntry()
  {
    return $this->entry;
  }
  /**
   * Output only. The constraint_name of the foreign key, for example,
   * FK_CustomerOrder.
   *
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
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey::class, 'Google_Service_DataCatalog_GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey');
