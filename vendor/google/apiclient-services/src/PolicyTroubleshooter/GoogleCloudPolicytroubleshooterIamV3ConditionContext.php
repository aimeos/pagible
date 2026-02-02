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

class GoogleCloudPolicytroubleshooterIamV3ConditionContext extends \Google\Collection
{
  protected $collection_key = 'effectiveTags';
  protected $destinationType = GoogleCloudPolicytroubleshooterIamV3ConditionContextPeer::class;
  protected $destinationDataType = '';
  protected $effectiveTagsType = GoogleCloudPolicytroubleshooterIamV3ConditionContextEffectiveTag::class;
  protected $effectiveTagsDataType = 'array';
  protected $requestType = GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest::class;
  protected $requestDataType = '';
  protected $resourceType = GoogleCloudPolicytroubleshooterIamV3ConditionContextResource::class;
  protected $resourceDataType = '';

  /**
   * The destination of a network activity, such as accepting a TCP connection.
   * In a multi-hop network activity, the destination represents the receiver of
   * the last hop.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionContextPeer $destination
   */
  public function setDestination(GoogleCloudPolicytroubleshooterIamV3ConditionContextPeer $destination)
  {
    $this->destination = $destination;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionContextPeer
   */
  public function getDestination()
  {
    return $this->destination;
  }
  /**
   * Output only. The effective tags on the resource. The effective tags are
   * fetched during troubleshooting.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionContextEffectiveTag[] $effectiveTags
   */
  public function setEffectiveTags($effectiveTags)
  {
    $this->effectiveTags = $effectiveTags;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionContextEffectiveTag[]
   */
  public function getEffectiveTags()
  {
    return $this->effectiveTags;
  }
  /**
   * Represents a network request, such as an HTTP request.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest $request
   */
  public function setRequest(GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest $request)
  {
    $this->request = $request;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionContextRequest
   */
  public function getRequest()
  {
    return $this->request;
  }
  /**
   * Represents a target resource that is involved with a network activity. If
   * multiple resources are involved with an activity, this must be the primary
   * one.
   *
   * @param GoogleCloudPolicytroubleshooterIamV3ConditionContextResource $resource
   */
  public function setResource(GoogleCloudPolicytroubleshooterIamV3ConditionContextResource $resource)
  {
    $this->resource = $resource;
  }
  /**
   * @return GoogleCloudPolicytroubleshooterIamV3ConditionContextResource
   */
  public function getResource()
  {
    return $this->resource;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudPolicytroubleshooterIamV3ConditionContext::class, 'Google_Service_PolicyTroubleshooter_GoogleCloudPolicytroubleshooterIamV3ConditionContext');
