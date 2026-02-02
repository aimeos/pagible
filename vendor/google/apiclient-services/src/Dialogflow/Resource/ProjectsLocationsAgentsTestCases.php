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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3BatchDeleteTestCasesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3BatchRunTestCasesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3CalculateCoverageResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportTestCasesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ImportTestCasesRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListTestCasesResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3RunTestCaseRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3TestCase;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "testCases" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $testCases = $dialogflowService->projects_locations_agents_testCases;
 *  </code>
 */
class ProjectsLocationsAgentsTestCases extends \Google\Service\Resource
{
  /**
   * (testCases.batchDelete)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3BatchDeleteTestCasesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleProtobufEmpty
   * @throws \Google\Service\Exception
   */
  public function batchDelete($parent, GoogleCloudDialogflowCxV3BatchDeleteTestCasesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('batchDelete', [$params], GoogleProtobufEmpty::class);
  }
  /**
   * (testCases.batchRun)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3BatchRunTestCasesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function batchRun($parent, GoogleCloudDialogflowCxV3BatchRunTestCasesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('batchRun', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (testCases.calculateCoverage)
   *
   * @param string $agent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string type
   * @return GoogleCloudDialogflowCxV3CalculateCoverageResponse
   * @throws \Google\Service\Exception
   */
  public function calculateCoverage($agent, $optParams = [])
  {
    $params = ['agent' => $agent];
    $params = array_merge($params, $optParams);
    return $this->call('calculateCoverage', [$params], GoogleCloudDialogflowCxV3CalculateCoverageResponse::class);
  }
  /**
   * (testCases.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3TestCase $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3TestCase
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3TestCase $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3TestCase::class);
  }
  /**
   * (testCases.export)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ExportTestCasesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($parent, GoogleCloudDialogflowCxV3ExportTestCasesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (testCases.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3TestCase
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3TestCase::class);
  }
  /**
   * (testCases.import)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ImportTestCasesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function import($parent, GoogleCloudDialogflowCxV3ImportTestCasesRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('import', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (testCases.listProjectsLocationsAgentsTestCases)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @opt_param string view
   * @return GoogleCloudDialogflowCxV3ListTestCasesResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsTestCases($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListTestCasesResponse::class);
  }
  /**
   * (testCases.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3TestCase $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3TestCase
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3TestCase $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3TestCase::class);
  }
  /**
   * (testCases.run)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3RunTestCaseRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function run($name, GoogleCloudDialogflowCxV3RunTestCaseRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('run', [$params], GoogleLongrunningOperation::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsTestCases::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsTestCases');
