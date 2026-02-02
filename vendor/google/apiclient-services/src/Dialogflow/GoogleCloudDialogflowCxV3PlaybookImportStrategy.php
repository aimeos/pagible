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

class GoogleCloudDialogflowCxV3PlaybookImportStrategy extends \Google\Model
{
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_UNSPECIFIED = 'IMPORT_STRATEGY_UNSPECIFIED';
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_CREATE_NEW = 'IMPORT_STRATEGY_CREATE_NEW';
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_REPLACE = 'IMPORT_STRATEGY_REPLACE';
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_KEEP = 'IMPORT_STRATEGY_KEEP';
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_MERGE = 'IMPORT_STRATEGY_MERGE';
  public const MAIN_PLAYBOOK_IMPORT_STRATEGY_IMPORT_STRATEGY_THROW_ERROR = 'IMPORT_STRATEGY_THROW_ERROR';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_UNSPECIFIED = 'IMPORT_STRATEGY_UNSPECIFIED';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_CREATE_NEW = 'IMPORT_STRATEGY_CREATE_NEW';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_REPLACE = 'IMPORT_STRATEGY_REPLACE';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_KEEP = 'IMPORT_STRATEGY_KEEP';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_MERGE = 'IMPORT_STRATEGY_MERGE';
  public const NESTED_RESOURCE_IMPORT_STRATEGY_IMPORT_STRATEGY_THROW_ERROR = 'IMPORT_STRATEGY_THROW_ERROR';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_UNSPECIFIED = 'IMPORT_STRATEGY_UNSPECIFIED';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_CREATE_NEW = 'IMPORT_STRATEGY_CREATE_NEW';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_REPLACE = 'IMPORT_STRATEGY_REPLACE';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_KEEP = 'IMPORT_STRATEGY_KEEP';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_MERGE = 'IMPORT_STRATEGY_MERGE';
  public const TOOL_IMPORT_STRATEGY_IMPORT_STRATEGY_THROW_ERROR = 'IMPORT_STRATEGY_THROW_ERROR';
  /**
   * @var string
   */
  public $mainPlaybookImportStrategy;
  /**
   * @var string
   */
  public $nestedResourceImportStrategy;
  /**
   * @var string
   */
  public $toolImportStrategy;

  /**
   * @param self::MAIN_PLAYBOOK_IMPORT_STRATEGY_* $mainPlaybookImportStrategy
   */
  public function setMainPlaybookImportStrategy($mainPlaybookImportStrategy)
  {
    $this->mainPlaybookImportStrategy = $mainPlaybookImportStrategy;
  }
  /**
   * @return self::MAIN_PLAYBOOK_IMPORT_STRATEGY_*
   */
  public function getMainPlaybookImportStrategy()
  {
    return $this->mainPlaybookImportStrategy;
  }
  /**
   * @param self::NESTED_RESOURCE_IMPORT_STRATEGY_* $nestedResourceImportStrategy
   */
  public function setNestedResourceImportStrategy($nestedResourceImportStrategy)
  {
    $this->nestedResourceImportStrategy = $nestedResourceImportStrategy;
  }
  /**
   * @return self::NESTED_RESOURCE_IMPORT_STRATEGY_*
   */
  public function getNestedResourceImportStrategy()
  {
    return $this->nestedResourceImportStrategy;
  }
  /**
   * @param self::TOOL_IMPORT_STRATEGY_* $toolImportStrategy
   */
  public function setToolImportStrategy($toolImportStrategy)
  {
    $this->toolImportStrategy = $toolImportStrategy;
  }
  /**
   * @return self::TOOL_IMPORT_STRATEGY_*
   */
  public function getToolImportStrategy()
  {
    return $this->toolImportStrategy;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3PlaybookImportStrategy::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3PlaybookImportStrategy');
