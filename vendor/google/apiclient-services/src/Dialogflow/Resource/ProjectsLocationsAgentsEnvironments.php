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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3DeployFlowRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Environment;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListEnvironmentsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3LookupEnvironmentHistoryResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3RunContinuousTestRequest;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "environments" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $environments = $dialogflowService->projects_locations_agents_environments;
 *  </code>
 */
class ProjectsLocationsAgentsEnvironments extends \Google\Service\Resource
{
  /**
   * (environments.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Environment $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Environment $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (environments.delete)
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
   * (environments.deployFlow)
   *
   * @param string $environment
   * @param GoogleCloudDialogflowCxV3DeployFlowRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function deployFlow($environment, GoogleCloudDialogflowCxV3DeployFlowRequest $postBody, $optParams = [])
  {
    $params = ['environment' => $environment, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('deployFlow', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (environments.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Environment
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Environment::class);
  }
  /**
   * (environments.listProjectsLocationsAgentsEnvironments)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListEnvironmentsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsEnvironments($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListEnvironmentsResponse::class);
  }
  /**
   * (environments.lookupEnvironmentHistory)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3LookupEnvironmentHistoryResponse
   * @throws \Google\Service\Exception
   */
  public function lookupEnvironmentHistory($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('lookupEnvironmentHistory', [$params], GoogleCloudDialogflowCxV3LookupEnvironmentHistoryResponse::class);
  }
  /**
   * (environments.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Environment $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Environment $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (environments.runContinuousTest)
   *
   * @param string $environment
   * @param GoogleCloudDialogflowCxV3RunContinuousTestRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function runContinuousTest($environment, GoogleCloudDialogflowCxV3RunContinuousTestRequest $postBody, $optParams = [])
  {
    $params = ['environment' => $environment, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('runContinuousTest', [$params], GoogleLongrunningOperation::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsEnvironments::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsEnvironments');
