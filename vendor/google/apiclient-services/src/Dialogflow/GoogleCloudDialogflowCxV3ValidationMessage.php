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

class GoogleCloudDialogflowCxV3ValidationMessage extends \Google\Collection
{
  public const RESOURCE_TYPE_RESOURCE_TYPE_UNSPECIFIED = 'RESOURCE_TYPE_UNSPECIFIED';
  public const RESOURCE_TYPE_AGENT = 'AGENT';
  public const RESOURCE_TYPE_INTENT = 'INTENT';
  public const RESOURCE_TYPE_INTENT_TRAINING_PHRASE = 'INTENT_TRAINING_PHRASE';
  public const RESOURCE_TYPE_INTENT_PARAMETER = 'INTENT_PARAMETER';
  public const RESOURCE_TYPE_INTENTS = 'INTENTS';
  public const RESOURCE_TYPE_INTENT_TRAINING_PHRASES = 'INTENT_TRAINING_PHRASES';
  public const RESOURCE_TYPE_ENTITY_TYPE = 'ENTITY_TYPE';
  public const RESOURCE_TYPE_ENTITY_TYPES = 'ENTITY_TYPES';
  public const RESOURCE_TYPE_WEBHOOK = 'WEBHOOK';
  public const RESOURCE_TYPE_FLOW = 'FLOW';
  public const RESOURCE_TYPE_PAGE = 'PAGE';
  public const RESOURCE_TYPE_PAGES = 'PAGES';
  public const RESOURCE_TYPE_TRANSITION_ROUTE_GROUP = 'TRANSITION_ROUTE_GROUP';
  public const RESOURCE_TYPE_AGENT_TRANSITION_ROUTE_GROUP = 'AGENT_TRANSITION_ROUTE_GROUP';
  public const SEVERITY_SEVERITY_UNSPECIFIED = 'SEVERITY_UNSPECIFIED';
  public const SEVERITY_INFO = 'INFO';
  public const SEVERITY_WARNING = 'WARNING';
  public const SEVERITY_ERROR = 'ERROR';
  protected $collection_key = 'resources';
  /**
   * @var string
   */
  public $detail;
  protected $resourceNamesType = GoogleCloudDialogflowCxV3ResourceName::class;
  protected $resourceNamesDataType = 'array';
  /**
   * @var string
   */
  public $resourceType;
  /**
   * @deprecated
   * @var string[]
   */
  public $resources;
  /**
   * @var string
   */
  public $severity;

  /**
   * @param string $detail
   */
  public function setDetail($detail)
  {
    $this->detail = $detail;
  }
  /**
   * @return string
   */
  public function getDetail()
  {
    return $this->detail;
  }
  /**
   * @param GoogleCloudDialogflowCxV3ResourceName[] $resourceNames
   */
  public function setResourceNames($resourceNames)
  {
    $this->resourceNames = $resourceNames;
  }
  /**
   * @return GoogleCloudDialogflowCxV3ResourceName[]
   */
  public function getResourceNames()
  {
    return $this->resourceNames;
  }
  /**
   * @param self::RESOURCE_TYPE_* $resourceType
   */
  public function setResourceType($resourceType)
  {
    $this->resourceType = $resourceType;
  }
  /**
   * @return self::RESOURCE_TYPE_*
   */
  public function getResourceType()
  {
    return $this->resourceType;
  }
  /**
   * @deprecated
   * @param string[] $resources
   */
  public function setResources($resources)
  {
    $this->resources = $resources;
  }
  /**
   * @deprecated
   * @return string[]
   */
  public function getResources()
  {
    return $this->resources;
  }
  /**
   * @param self::SEVERITY_* $severity
   */
  public function setSeverity($severity)
  {
    $this->severity = $severity;
  }
  /**
   * @return self::SEVERITY_*
   */
  public function getSeverity()
  {
    return $this->severity;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowCxV3ValidationMessage::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowCxV3ValidationMessage');
