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

class GoogleCloudDialogflowCxV3SafetySettingsRaiSettingsCategoryFilter extends \Google\Model
{
  public const CATEGORY_SAFETY_CATEGORY_UNSPECIFIED = 'SAFETY_CATEGORY_UNSPECIFIED';
  public const CATEGORY_DANGEROUS_CONTENT = 'DANGEROUS_CONTENT';
  public const CATEGORY_HATE_SPEECH = 'HATE_SPEECH';
  public const CATEGORY_HARASSMENT = 'HARASSMENT';
  public const CATEGORY_SEXUALLY_EXPLICIT_CONTENT = 'SEXUALLY_EXPLICIT_CONTENT';
  public const FILTER_LEVEL_SAFETY_FILTER_LEVEL_UNSPECIFIED = 'SAFETY_FILTER_LEVEL_UNSPECIFIED';
  public const FILTER_LEVEL_BLOCK_NONE = 'BLOCK_NONE';
  public const FILTER_LEVEL_BLOCK_FEW = 'BLOCK_FEW';
  public const FILTER_LEVEL_BLOCK_SOME = 'BLOCK_SOME';
  public const FILTER_LEVEL_BLOCK_MOST = 'BLOCK_MOST';
  /**
   * @var string
   */
  public $category;
  /**
   * @var string
   */
  public $filterLevel;

  /**
   * @param self::CATEGORY_* $category
   */
  public function setCategory($category)
  {
    $this->category = $category;
  }
  /**
   * @return self::CATEGORY_*
   */
  public function getCategory()
  {
    return $this->category;
  }
  /**
   * @param self::FILTER_LEVEL_* $filterLevel
   */
  public function setFilterLevel($filterLevel)
  {
    $this->filterLevel = $filterLevel;
  }
  /**
   * @return self::FILTER_LEVEL_*
   */
  public function getFilterLevel()
  {
    return $this->filterLevel;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3SafetySettingsRaiSettingsCategoryFilter::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3SafetySettingsRaiSettingsCategoryFilter');
