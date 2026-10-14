<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\File;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('publish-file')]
#[Title('Publish one or more media files')]
#[Description('Publishes one or more files by ID. Pass an array of up to 50 UUIDs. Optionally schedule via "at". Returns published and skipped items.')]
class PublishFile extends PublishTool
{
    protected const PERMISSIONS = ['file:publish'];
    protected const MODEL = File::class;
    protected const NOUN = 'file';
}
