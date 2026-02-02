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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListTestCaseResultsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3TestCaseResult;

/**
 * The "results" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $results = $dialogflowService->projects_locations_agents_testCases_results;
 *  </code>
 */
class ProjectsLocationsAgentsTestCasesResults extends \Google\Service\Resource
{
  /**
   * (results.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3TestCaseResult
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3TestCaseResult::class);
  }
  /**
   * (results.listProjectsLocationsAgentsTestCasesResults)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string filter
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListTestCaseResultsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsTestCasesResults($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListTestCaseResultsResponse::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsTestCasesResults::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsTestCasesResults');
