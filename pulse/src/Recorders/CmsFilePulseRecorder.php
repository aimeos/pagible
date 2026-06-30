<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;


class CmsFilePulseRecorder extends ContentPulseRecorder
{
    protected string $contentType = 'file';
    protected string $pulseType = 'cms_file';
}
