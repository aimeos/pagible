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

namespace Google\Service\Dialogflow;

class GoogleCloudDialogflowV2beta1IntentMessageRbmStandaloneCard extends \Google\Model
{
  public const CARD_ORIENTATION_CARD_ORIENTATION_UNSPECIFIED = 'CARD_ORIENTATION_UNSPECIFIED';
  public const CARD_ORIENTATION_HORIZONTAL = 'HORIZONTAL';
  public const CARD_ORIENTATION_VERTICAL = 'VERTICAL';
  public const THUMBNAIL_IMAGE_ALIGNMENT_THUMBNAIL_IMAGE_ALIGNMENT_UNSPECIFIED = 'THUMBNAIL_IMAGE_ALIGNMENT_UNSPECIFIED';
  public const THUMBNAIL_IMAGE_ALIGNMENT_LEFT = 'LEFT';
  public const THUMBNAIL_IMAGE_ALIGNMENT_RIGHT = 'RIGHT';
  protected $cardContentType = GoogleCloudDialogflowV2beta1IntentMessageRbmCardContent::class;
  protected $cardContentDataType = '';
  /**
   * @var string
   */
  public $cardOrientation;
  /**
   * @var string
   */
  public $thumbnailImageAlignment;

  /**
   * @param GoogleCloudDialogflowV2beta1IntentMessageRbmCardContent $cardContent
   */
  public function setCardContent(GoogleCloudDialogflowV2beta1IntentMessageRbmCardContent $cardContent)
  {
    $this->cardContent = $cardContent;
  }
  /**
   * @return GoogleCloudDialogflowV2beta1IntentMessageRbmCardContent
   */
  public function getCardContent()
  {
    return $this->cardContent;
  }
  /**
   * @param self::CARD_ORIENTATION_* $cardOrientation
   */
  public function setCardOrientation($cardOrientation)
  {
    $this->cardOrientation = $cardOrientation;
  }
  /**
   * @return self::CARD_ORIENTATION_*
   */
  public function getCardOrientation()
  {
    return $this->cardOrientation;
  }
  /**
   * @param self::THUMBNAIL_IMAGE_ALIGNMENT_* $thumbnailImageAlignment
   */
  public function setThumbnailImageAlignment($thumbnailImageAlignment)
  {
    $this->thumbnailImageAlignment = $thumbnailImageAlignment;
  }
  /**
   * @return self::THUMBNAIL_IMAGE_ALIGNMENT_*
   */
  public function getThumbnailImageAlignment()
  {
    return $this->thumbnailImageAlignment;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(GoogleCloudDialogflowV2beta1IntentMessageRbmStandaloneCard::class, 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1IntentMessageRbmStandaloneCard');
