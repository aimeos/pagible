<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Elements;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('publish-element')]
#[Title('Publish one or more shared content elements')]
#[Description('Publishes one or more shared elements by ID. Pass an array of up to 50 UUIDs. Optionally schedule via "at". Returns published and skipped items.')]
class PublishElement extends PublishTool
{
    protected const PERMISSIONS = ['element:publish'];
    protected const RESOURCE = Elements::class;
    protected const NOUN = 'element';
}
