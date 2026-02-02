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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3CompareVersionsRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3CompareVersionsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListVersionsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3LoadVersionRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Version;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "versions" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $versions = $dialogflowService->projects_locations_agents_flows_versions;
 *  </code>
 */
class ProjectsLocationsAgentsFlowsVersions extends \Google\Service\Resource
{
  /**
   * (versions.compareVersions)
   *
   * @param string $baseVersion
   * @param GoogleCloudDialogflowCxV3CompareVersionsRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3CompareVersionsResponse
   * @throws \Google\Service\Exception
   */
  public function compareVersions($baseVersion, GoogleCloudDialogflowCxV3CompareVersionsRequest $postBody, $optParams = [])
  {
    $params = ['baseVersion' => $baseVersion, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('compareVersions', [$params], GoogleCloudDialogflowCxV3CompareVersionsResponse::class);
  }
  /**
   * (versions.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Version $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Version $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (versions.delete)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
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
   * @return GoogleCloudDialogflowCxV3Version
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Version::class);
  }
  /**
   * (versions.listProjectsLocationsAgentsFlowsVersions)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListVersionsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsFlowsVersions($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListVersionsResponse::class);
  }
  /**
   * (versions.load)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3LoadVersionRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function load($name, GoogleCloudDialogflowCxV3LoadVersionRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('load', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (versions.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Version $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Version
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Version $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Version::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsFlowsVersions::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsFlowsVersions');
