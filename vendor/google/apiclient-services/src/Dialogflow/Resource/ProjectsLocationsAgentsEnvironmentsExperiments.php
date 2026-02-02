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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3Experiment;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListExperimentsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3StartExperimentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3StopExperimentRequest;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "experiments" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $experiments = $dialogflowService->projects_locations_agents_environments_experiments;
 *  </code>
 */
class ProjectsLocationsAgentsEnvironmentsExperiments extends \Google\Service\Resource
{
  /**
   * (experiments.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3Experiment $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Experiment
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3Experiment $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3Experiment::class);
  }
  /**
   * (experiments.delete)
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
   * (experiments.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Experiment
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3Experiment::class);
  }
  /**
   * (experiments.listProjectsLocationsAgentsEnvironmentsExperiments)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListExperimentsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsAgentsEnvironmentsExperiments($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListExperimentsResponse::class);
  }
  /**
   * (experiments.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3Experiment $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3Experiment
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3Experiment $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3Experiment::class);
  }
  /**
   * (experiments.start)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3StartExperimentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Experiment
   * @throws \Google\Service\Exception
   */
  public function start($name, GoogleCloudDialogflowCxV3StartExperimentRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('start', [$params], GoogleCloudDialogflowCxV3Experiment::class);
  }
  /**
   * (experiments.stop)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3StopExperimentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3Experiment
   * @throws \Google\Service\Exception
   */
  public function stop($name, GoogleCloudDialogflowCxV3StopExperimentRequest $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('stop', [$params], GoogleCloudDialogflowCxV3Experiment::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsEnvironmentsExperiments::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsEnvironmentsExperiments');
