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

namespace Google\Service\Dialogflow\Resource;

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3EntityType;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportEntityTypesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ImportEntityTypesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListEntityTypesResponse;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "entityTypes" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $entityTypes = $dialogflowService->projects_locations_agents_entityTypes;
 *  </code>
 */
class ProjectsLocationsAgentsEntityTypes extends \Google\Service\Resource
{
  /**
   * (entityTypes.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3EntityType $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3EntityType
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3EntityType $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3EntityType::class);
  }
  /**
   * (entityTypes.delete)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param bool force
   * @return GoogleProtobufEmpty
   * @throws \Google\Service\Exception
   */
  public function delete($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('delete', [$params], GoogleProtobufEmpty::class);
  }
  /**
   * (entityTypes.export)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ExportEntityTypesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($parent, GoogleCloudDialogflowCxV3ExportEntityTypesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (entityTypes.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3EntityType
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3EntityType::class);
  }
  /**
   * (entityTypes.import)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ImportEntityTypesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function import($parent, GoogleCloudDialogflowCxV3ImportEntityTypesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('import', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (entityTypes.listProjectsLocationsAgentsEntityTypes)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListEntityTypesResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsEntityTypes($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListEntityTypesResponse::class);
  }
  /**
   * (entityTypes.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3EntityType $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3EntityType
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3EntityType $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3EntityType::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsEntityTypes::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsEntityTypes');
