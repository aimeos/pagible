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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportIntentsRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ImportIntentsRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Intent;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListIntentsResponse;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "intents" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $intents = $dialogflowService->projects_locations_agents_intents;
 *  </code>
 */
class ProjectsLocationsAgentsIntents extends \Google\Service\Resource
{
  /**
   * (intents.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Intent $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Intent
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Intent $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Intent::class);
  }
  /**
   * (intents.delete)
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
   * (intents.export)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ExportIntentsRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($parent, GoogleCloudDialogflowCxV3ExportIntentsRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (intents.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Intent
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Intent::class);
  }
  /**
   * (intents.import)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ImportIntentsRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function import($parent, GoogleCloudDialogflowCxV3ImportIntentsRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('import', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (intents.listProjectsLocationsAgentsIntents)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string intentView
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListIntentsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsIntents($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListIntentsResponse::class);
  }
  /**
   * (intents.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Intent $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Intent
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Intent $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Intent::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsIntents::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsIntents');
