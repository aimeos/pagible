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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Agent;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3AgentValidationResult;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportAgentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3GenerativeSettings;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListAgentsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3RestoreAgentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ValidateAgentRequest;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "agents" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $agents = $dialogflowService->projects_locations_agents;
 *  </code>
 */
class ProjectsLocationsAgents extends \Google\Service\Resource
{
  /**
   * (agents.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Agent $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Agent
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Agent $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Agent::class);
  }
  /**
   * (agents.delete)
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
   * (agents.export)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3ExportAgentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($name, GoogleCloudDialogflowCxV3ExportAgentRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (agents.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Agent
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Agent::class);
  }
  /**
   * (agents.getGenerativeSettings)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3GenerativeSettings
   * @throws \Google\Service\Exception
   */
  public function getGenerativeSettings($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('getGenerativeSettings', [$params], GoogleCloudDialogflowCxV3GenerativeSettings::class);
  }
  /**
   * (agents.getValidationResult)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3AgentValidationResult
   * @throws \Google\Service\Exception
   */
  public function getValidationResult($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('getValidationResult', [$params], GoogleCloudDialogflowCxV3AgentValidationResult::class);
  }
  /**
   * (agents.listProjectsLocationsAgents)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListAgentsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgents($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListAgentsResponse::class);
  }
  /**
   * (agents.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Agent $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Agent
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Agent $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Agent::class);
  }
  /**
   * (agents.restore)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3RestoreAgentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function restore($name, GoogleCloudDialogflowCxV3RestoreAgentRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('restore', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (agents.updateGenerativeSettings)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3GenerativeSettings $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3GenerativeSettings
   * @throws \Google\Service\Exception
   */
  public function updateGenerativeSettings($name, GoogleCloudDialogflowCxV3GenerativeSettings $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('updateGenerativeSettings', [$params], GoogleCloudDialogflowCxV3GenerativeSettings::class);
  }
  /**
   * (agents.validate)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3ValidateAgentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3AgentValidationResult
   * @throws \Google\Service\Exception
   */
  public function validate($name, GoogleCloudDialogflowCxV3ValidateAgentRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('validate', [$params], GoogleCloudDialogflowCxV3AgentValidationResult::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgents::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgents');
