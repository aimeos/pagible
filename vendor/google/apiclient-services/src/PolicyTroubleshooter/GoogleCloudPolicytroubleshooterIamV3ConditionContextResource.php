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

namespace Google\Service\PolicyTroubleshooter;

class GoogleCloudPolicytroubleshooterIamV3ConditionContextResource extends \Google\Model
{
  /**
   * The stable identifier (name) of a resource on the `service`. A resource can
   * be logically identified as `//{resource.service}/{resource.name}`. Unlike
   * the resource URI, the resource name doesn't contain any protocol and
   * version information. For a list of full resource name formats, see
   * https://cloud.google.com/iam/help/troubleshooter/full-resource-names
   *
   * @var string
   */
  public $name;
  /**
   * The name of the service that this resource belongs to, such as
   * `compute.googleapis.com`. The service name might not match the DNS hostname
   * that actually serves the request. For a full list of resource service
   * values, see https://cloud.google.com/iam/help/conditions/resource-services
   *
   * @var string
   */
  public $service;
  /**
   * The type of the resource, in the format `{service}/{kind}`. For a full list
   * of resource type values, see
   * https://cloud.google.com/iam/help/conditions/resource-types
   *
   * @var string
   */
  public $type;

  /**
   * The stable identifier (name) of a resource on the `service`. A resource can
   * be logically identified as `//{resource.service}/{resource.name}`. Unlike
   * the resource URI, the resource name doesn't contain any protocol and
   * version information. For a list of full resource name formats, see
   * https://cloud.google.com/iam/help/troubleshooter/full-resource-names
   *
   * @param string $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }
  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }
  /**
   * The name of the service that this resource belongs to, such as
   * `compute.googleapis.com`. The service name might not match the DNS hostname
   * that actually serves the request. For a full list of resource service
   * values, see https://cloud.google.com/iam/help/conditions/resource-services
   *
   * @param string $service
   */
  public function setService($service)
  {
    $this->service = $service;
  }
  /**
   * @return string
   */
  public function getService()
  {
    return $this->service;
  }
  /**
   * The type of the resource, in the format `{service}/{kind}`. For a full list
   * of resource type values, see
   * https://cloud.google.com/iam/help/conditions/resource-types
   *
   * @param string $type
   */
  public function setType($type)
  {
    $this->type = $type;
  }
  /**
   * @return string
   */
  public function getType()
  {
    return $this->type;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ConditionContextResource::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ConditionContextResource');
