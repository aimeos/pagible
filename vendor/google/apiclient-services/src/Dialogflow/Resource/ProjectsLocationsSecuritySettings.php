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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3ListSecuritySettingsResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3SecuritySettings;
use Google\Service\Dialogflow\GoogleProtobufEmpty;

/**
 * The "securitySettings" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $securitySettings = $dialogflowService->projects_locations_securitySettings;
 *  </code>
 */
class ProjectsLocationsSecuritySettings extends \Google\Service\Resource
{
  /**
   * (securitySettings.create)
   *
   * @param string $parent
   * @param GoogleCloudDialogflowCxV3SecuritySettings $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3SecuritySettings
   * @throws \Google\Service\Exception
   */
  public function create($parent, GoogleCloudDialogflowCxV3SecuritySettings $postBody, $optParams = [])
  {
    $params = ['parent' => $parent, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], GoogleCloudDialogflowCxV3SecuritySettings::class);
  }
  /**
   * (securitySettings.delete)
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
   * (securitySettings.get)
   *
   * @param string $name
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3SecuritySettings
   * @throws \Google\Service\Exception
   */
  public function get($name, $optParams = [])
  {
    $params = ['name' => $name];
    $params = array_merge($params, $optParams);
    return $this->call('get', [$params], GoogleCloudDialogflowCxV3SecuritySettings::class);
  }
  /**
   * (securitySettings.listProjectsLocationsSecuritySettings)
   *
   * @param string $parent
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize
   * @opt_param string pageToken
   * @return GoogleCloudDialogflowCxV3ListSecuritySettingsResponse
   * @throws \Google\Service\Exception
   */
  public function listProjectsLocationsSecuritySettings($parent, $optParams = [])
  {
    $params = ['parent' => $parent];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], GoogleCloudDialogflowCxV3ListSecuritySettingsResponse::class);
  }
  /**
   * (securitySettings.patch)
   *
   * @param string $name
   * @param GoogleCloudDialogflowCxV3SecuritySettings $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask
   * @return GoogleCloudDialogflowCxV3SecuritySettings
   * @throws \Google\Service\Exception
   */
  public function patch($name, GoogleCloudDialogflowCxV3SecuritySettings $postBody, $optParams = [])
  {
    $params = ['name' => $name, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], GoogleCloudDialogflowCxV3SecuritySettings::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsSecuritySettings::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsSecuritySettings');
