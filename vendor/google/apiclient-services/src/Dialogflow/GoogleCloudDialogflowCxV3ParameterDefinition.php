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

class GoogleCloudDialogflowCxV3ParameterDefinition extends \Google\Model
{
  public const TYPE_PARAMETER_TYPE_UNSPECIFIED = 'PARAMETER_TYPE_UNSPECIFIED';
  public const TYPE_STRING = 'STRING';
  public const TYPE_NUMBER = 'NUMBER';
  public const TYPE_BOOLEAN = 'BOOLEAN';
  public const TYPE_NULL = 'NULL';
  public const TYPE_OBJECT = 'OBJECT';
  public const TYPE_LIST = 'LIST';
  /**
   * @var string
   */
  public $description;
  /**
   * @var string
   */
  public $name;
  /**
   * @deprecated
   * @var string
   */
  public $type;
  protected $typeSchemaType = GoogleCloudDialogflowCxV3TypeSchema::class;
  protected $typeSchemaDataType = '';

  /**
   * @param string $description
   */
  public function setDescription($description)
  {
    $this->description = $description;
  }
  /**
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
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
   * @deprecated
   * @param self::TYPE_* $type
   */
  public function setType($type)
  {
    $this->type = $type;
  }
  /**
   * @deprecated
   * @return self::TYPE_*
   */
  public function getType()
  {
    return $this->type;
  }
  /**
   * @param GoogleCloudDialogflowCxV3TypeSchema $typeSchema
   */
  public function setTypeSchema(GoogleCloudDialogflowCxV3TypeSchema $typeSchema)
  {
    $this->typeSchema = $typeSchema;
  }
  /**
   * @return GoogleCloudDialogflowCxV3TypeSchema
   */
  public function getTypeSchema()
  {
    return $this->typeSchema;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3ParameterDefinition::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3ParameterDefinition');
