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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListToolVersionsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3RestoreToolVersionRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3RestoreToolVersionResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ToolVersion;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "versions" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $versions = $dialogflowService->projects_locations_agents_tools_versions;
 *  </code>
 */
class ProjectsLocationsAgentsToolsVersions extends \Google\Service\Resource
{
  /**
   * (versions.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ToolVersion $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3ToolVersion
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3ToolVersion $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3ToolVersion::class);
  }
  /**
   * (versions.delete)
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
   * (versions.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3ToolVersion
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3ToolVersion::class);
  }
  /**
   * (versions.listProjectsLocationsAgentsToolsVersions)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListToolVersionsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsToolsVersions($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListToolVersionsResponse::class);
  }
  /**
   * (versions.restore)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3RestoreToolVersionRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3RestoreToolVersionResponse
   * @throws \Google\Service\Exception
   */
  public function restore($name, GoogleCloudDialogflowCxV3RestoreToolVersionRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('restore', [$params], GoogleCloudDialogflowCxV3RestoreToolVersionResponse::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsToolsVersions::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsToolsVersions');
