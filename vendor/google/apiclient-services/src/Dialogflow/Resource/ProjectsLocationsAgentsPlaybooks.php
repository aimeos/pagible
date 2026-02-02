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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportPlaybookRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ImportPlaybookRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListPlaybooksResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Playbook;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "playbooks" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $playbooks = $dialogflowService->projects_locations_agents_playbooks;
 *  </code>
 */
class ProjectsLocationsAgentsPlaybooks extends \Google\Service\Resource
{
  /**
   * (playbooks.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Playbook $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Playbook
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Playbook $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Playbook::class);
  }
  /**
   * (playbooks.delete)
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
   * (playbooks.export)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3ExportPlaybookRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($name, GoogleCloudDialogflowCxV3ExportPlaybookRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (playbooks.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Playbook
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Playbook::class);
  }
  /**
   * (playbooks.import)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ImportPlaybookRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function import($parent, GoogleCloudDialogflowCxV3ImportPlaybookRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('import', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (playbooks.listProjectsLocationsAgentsPlaybooks)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListPlaybooksResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsPlaybooks($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListPlaybooksResponse::class);
  }
  /**
   * (playbooks.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Playbook $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Playbook
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Playbook $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Playbook::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsPlaybooks::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsPlaybooks');
