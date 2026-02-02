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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Example;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListExamplesResponse;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "examples" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $examples = $dialogflowService->projects_locations_agents_playbooks_examples;
 *  </code>
 */
class ProjectsLocationsAgentsPlaybooksExamples extends \Google\Service\Resource
{
  /**
   * (examples.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Example $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Example
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Example $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Example::class);
  }
  /**
   * (examples.delete)
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
   * (examples.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Example
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Example::class);
  }
  /**
   * (examples.listProjectsLocationsAgentsPlaybooksExamples)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListExamplesResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsPlaybooksExamples($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListExamplesResponse::class);
  }
  /**
   * (examples.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Example $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Example
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Example $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Example::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsPlaybooksExamples::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsPlaybooksExamples');
