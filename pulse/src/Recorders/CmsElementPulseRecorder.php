<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;


class CmsElementPulseRecorder extends ContentPulseRecorder
{
    protected string $contentType = 'element';
    protected string $pulseType = 'cms_element';
}
