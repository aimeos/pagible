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

use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3AnswerFeedback;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3DetectIntentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3DetectIntentResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3FulfillIntentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3FulfillIntentResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3MatchIntentRequest;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3MatchIntentResponse;
use Google\Service\Dialogflow\GoogleCloudDialogflowCxV3SubmitAnswerFeedbackRequest;

/**
 * The "sessions" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dialogflowService = new Google\Service\Dialogflow(...);
 *   $sessions = $dialogflowService->projects_locations_agents_sessions;
 *  </code>
 */
class ProjectsLocationsAgentsSessions extends \Google\Service\Resource
{
  /**
   * (sessions.detectIntent)
   *
   * @param string $session
   * @param GoogleCloudDialogflowCxV3DetectIntentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3DetectIntentResponse
   * @throws \Google\Service\Exception
   */
  public function detectIntent($session, GoogleCloudDialogflowCxV3DetectIntentRequest $postBody, $optParams = [])
  {
    $params = ['session' => $session, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('detectIntent', [$params], GoogleCloudDialogflowCxV3DetectIntentResponse::class);
  }
  /**
   * (sessions.fulfillIntent)
   *
   * @param string $session
   * @param GoogleCloudDialogflowCxV3FulfillIntentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3FulfillIntentResponse
   * @throws \Google\Service\Exception
   */
  public function fulfillIntent($session, GoogleCloudDialogflowCxV3FulfillIntentRequest $postBody, $optParams = [])
  {
    $params = ['session' => $session, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('fulfillIntent', [$params], GoogleCloudDialogflowCxV3FulfillIntentResponse::class);
  }
  /**
   * (sessions.matchIntent)
   *
   * @param string $session
   * @param GoogleCloudDialogflowCxV3MatchIntentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3MatchIntentResponse
   * @throws \Google\Service\Exception
   */
  public function matchIntent($session, GoogleCloudDialogflowCxV3MatchIntentRequest $postBody, $optParams = [])
  {
    $params = ['session' => $session, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('matchIntent', [$params], GoogleCloudDialogflowCxV3MatchIntentResponse::class);
  }
  /**
   * (sessions.serverStreamingDetectIntent)
   *
   * @param string $session
   * @param GoogleCloudDialogflowCxV3DetectIntentRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3DetectIntentResponse
   * @throws \Google\Service\Exception
   */
  public function serverStreamingDetectIntent($session, GoogleCloudDialogflowCxV3DetectIntentRequest $postBody, $optParams = [])
  {
    $params = ['session' => $session, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('serverStreamingDetectIntent', [$params], GoogleCloudDialogflowCxV3DetectIntentResponse::class);
  }
  /**
   * (sessions.submitAnswerFeedback)
   *
   * @param string $session
   * @param GoogleCloudDialogflowCxV3SubmitAnswerFeedbackRequest $postBody
   * @param array $optParams Optional parameters.
   * @return GoogleCloudDialogflowCxV3AnswerFeedback
   * @throws \Google\Service\Exception
   */
  public function submitAnswerFeedback($session, GoogleCloudDialogflowCxV3SubmitAnswerFeedbackRequest $postBody, $optParams = [])
  {
    $params = ['session' => $session, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('submitAnswerFeedback', [$params], GoogleCloudDialogflowCxV3AnswerFeedback::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ProjectsLocationsAgentsSessions::class, 'Google_Service_Dialogflow_Resource_ProjectsLocationsAgentsSessions');
