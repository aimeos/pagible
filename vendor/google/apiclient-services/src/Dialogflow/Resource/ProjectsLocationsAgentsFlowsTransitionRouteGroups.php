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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListTransitionRouteGroupsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3TransitionRouteGroup;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "transitionRouteGroups" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $transitionRouteGroups = $dialogflowService->projects_locations_agents_flows_transitionRouteGroups;
 *  </code>
 */
class ProjectsLocationsAgentsFlowsTransitionRouteGroups extends \Google\Service\Resource
{
  /**
   * (transitionRouteGroups.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3TransitionRouteGroup $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3TransitionRouteGroup
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3TransitionRouteGroup $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3TransitionRouteGroup::class);
  }
  /**
   * (transitionRouteGroups.delete)
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
   * (transitionRouteGroups.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3TransitionRouteGroup
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3TransitionRouteGroup::class);
  }
  /**
   * (transitionRouteGroups.listProjectsLocationsAgentsFlowsTransitionRouteGroups)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListTransitionRouteGroupsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsFlowsTransitionRouteGroups($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListTransitionRouteGroupsResponse::class);
  }
  /**
   * (transitionRouteGroups.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3TransitionRouteGroup $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3TransitionRouteGroup
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3TransitionRouteGroup $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3TransitionRouteGroup::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsFlowsTransitionRouteGroups::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsFlowsTransitionRouteGroups');
