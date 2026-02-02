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

class GoogleCloudDatacatalogV1SpannerTableSpec extends \Google\Collection
{
  protected $collection_key = 'foreignKeys';
  protected $foreignKeysType = GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey::class;
  protected $foreignKeysDataType = 'array';
  protected $primaryKeyType = GoogleCloudDatacatalogV1SpannerTableSpecSpannerPrimaryKey::class;
  protected $primaryKeyDataType = '';

  /**
   * Output only. The foreign keys of the table.
   *
   * @param GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey[] $foreignKeys
   */
  public function setForeignKeys($foreignKeys)
  {
    $this->foreignKeys = $foreignKeys;
  }
  /**
   * @return GoogleCloudDatacatalogV1SpannerTableSpecSpannerForeignKey[]
   */
  public function getForeignKeys()
  {
    return $this->foreignKeys;
  }
  /**
   * Output only. The primary key of the table.
   *
   * @param GoogleCloudDatacatalogV1SpannerTableSpecSpannerPrimaryKey $primaryKey
   */
  public function setPrimaryKey(GoogleCloudDatacatalogV1SpannerTableSpecSpannerPrimaryKey $primaryKey)
  {
    $this->primaryKey = $primaryKey;
  }
  /**
   * @return GoogleCloudDatacatalogV1SpannerTableSpecSpannerPrimaryKey
   */
  public function getPrimaryKey()
  {
    return $this->primaryKey;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDatacatalogV1SpannerTableSpec::class, 'Google_Service_DataCatalog_GoogleCloudDatacatalogV1SpannerTableSpec');
