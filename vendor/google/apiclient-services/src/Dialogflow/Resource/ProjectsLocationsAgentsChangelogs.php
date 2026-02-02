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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Changelog;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListChangelogsResponse;

/**
 * The "changelogs" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $changelogs = $dialogflowService->projects_locations_agents_changelogs;
 *  </code>
 */
class ProjectsLocationsAgentsChangelogs extends \Google\Service\Resource
{
  /**
   * (changelogs.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Changelog
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Changelog::class);
  }
  /**
   * (changelogs.listProjectsLocationsAgentsChangelogs)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string filter
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListChangelogsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsChangelogs($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListChangelogsResponse::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsChangelogs::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsChangelogs');
