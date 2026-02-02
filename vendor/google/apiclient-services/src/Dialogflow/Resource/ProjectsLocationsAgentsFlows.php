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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ExportFlowRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Flow;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3FlowValidationResult;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ImportFlowRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListFlowsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3TrainFlowRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ValidateFlowRequest;
use Google\Service\Dialogflow\GoogleLongrunningOperation;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "flows" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $flows = $dialogflowService->projects_locations_agents_flows;
 *  </code>
 */
class ProjectsLocationsAgentsFlows extends \Google\Service\Resource
{
  /**
   * (flows.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Flow $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Flow
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Flow $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Flow::class);
  }
  /**
   * (flows.delete)
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
   * (flows.export)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3ExportFlowRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function export($name, GoogleCloudDialogflowCxV3ExportFlowRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('export', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (flows.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3Flow
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Flow::class);
  }
  /**
   * (flows.getValidationResult)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @return GoogleCloudDialogflowCxV3FlowValidationResult
   * @throws \Google\Service\Exception
   */
  public function getValidationResult($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('getValidationResult', [$params], GoogleCloudDialogflowCxV3FlowValidationResult::class);
  }
  /**
   * (flows.import)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3ImportFlowRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function import($parent, GoogleCloudDialogflowCxV3ImportFlowRequest $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('import', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (flows.listProjectsLocationsAgentsFlows)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListFlowsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsFlows($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListFlowsResponse::class);
  }
  /**
   * (flows.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Flow $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string languageCode
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Flow
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Flow $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Flow::class);
  }
  /**
   * (flows.train)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3TrainFlowRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleLongrunningOperation
   * @throws \Google\Service\Exception
   */
  public function train($name, GoogleCloudDialogflowCxV3TrainFlowRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('train', [$params], GoogleLongrunningOperation::class);
  }
  /**
   * (flows.validate)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3ValidateFlowRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3FlowValidationResult
   * @throws \Google\Service\Exception
   */
  public function validate($name, GoogleCloudDialogflowCxV3ValidateFlowRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('validate', [$params], GoogleCloudDialogflowCxV3FlowValidationResult::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsFlows::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsFlows');
