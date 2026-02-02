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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Generator;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListGeneratorsResponse;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "generators" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $generators = $dialogflowService->projects_locations_agents_generators;
 *  </code>
 */
class ProjectsLocationsAgentsGenerators extends \Google\Service\Resource
{
  /**
   * (generators.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Generator $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Generator
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Generator $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Generator::class);
  }
  /**
   * (generators.delete)
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
   * (generators.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Generator
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Generator::class);
  }
  /**
   * (generators.listProjectsLocationsAgentsGenerators)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListGeneratorsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsGenerators($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListGeneratorsResponse::class);
  }
  /**
   * (generators.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Generator $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Generator
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Generator $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Generator::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsGenerators::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsGenerators');
