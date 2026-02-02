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

namespace Google\Service\Safebrowsing;

class GoogleSecuritySafebrowsingV5ThreatUrl extends \Google\Collection
{
  protected $collection_key = 'threatTypes';
  /**
   * Unordered list. The unordered list of threat that the URL is classified as.
   *
   * @var string[]
   */
  public $threatTypes;
  /**
   * The requested URL that was matched by one or more threats.
   *
   * @var string
   */
  public $url;

  /**
   * Unordered list. The unordered list of threat that the URL is classified as.
   *
   * @param string[] $threatTypes
   */
  public function setThreatTypes($threatTypes)
  {
    $this->threatTypes = $threatTypes;
  }
  /**
   * @return string[]
   */
  public function getThreatTypes()
  {
    return $this->threatTypes;
  }
  /**
   * The requested URL that was matched by one or more threats.
   *
   * @param string $url
   */
  public function setUrl($url)
  {
    $this->url = $url;
  }
  /**
   * @return string
   */
  public function getUrl()
  {
    return $this->url;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleSecuritySafebrowsingV5ThreatUrl::class, 'Google_Service_Safebrowsing_GoogleSecuritySafebrowsingV5ThreatUrl');
