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

namespace Google\Service\Safebrowsing\Resource;

use Google\Service\Safebrowsing\GoogleSecuritySafebrowsingV5SearchUrlsResponse;

/**
 * The "urls" collection of methods.
 * Typical usage is:
 *  <code>
 *   $safebrowsingService = new Google\Service\Safebrowsing(...);
 *   $urls = $safebrowsingService->urls;
 *  </code>
 */
class Urls extends \Google\Service\Resource
{
  /**
   * Searches for URLs matching known threats. Each URL and it's host-suffix and
   * path-prefix expressions (up to a limited depth) are checked. This means that
   * the response may contain URLs that were not included in the request, but are
   * expressions of the requested URLs. (urls.search)
   *
   * @param array $optParams Optional parameters.
   *
   * @opt_param string urls Required. The URLs to be looked up. Clients MUST NOT
   * send more than 50 URLs.
   * @return GoogleSecuritySafebrowsingV5SearchUrlsResponse
   * @throws \Google\Service\Exception
   */
  public function search($optParams = [])
  {
    $params = [];
    $params = array_merge($params, $optParams);
    return $this->call('search', [$params], GoogleSecuritySafebrowsingV5SearchUrlsResponse::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(Urls::class, 'Google_Service_Safebrowsing_Resource_Urls');
