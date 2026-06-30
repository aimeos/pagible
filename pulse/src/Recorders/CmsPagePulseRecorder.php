<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;


class CmsPagePulseRecorder extends ContentPulseRecorder
{
    protected string $contentType = 'page';
    protected string $pulseType = 'cms_page';
}
