<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Elements;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('drop-element')]
#[Title('Soft-delete a shared content element')]
#[Description('Soft-deletes a shared content element. The element can be restored within the retention period. Returns the deleted element as a JSON object.')]
class DropElement extends DropTool
{
    protected const PERMISSIONS = ['element:drop'];
    protected const RESOURCE = Elements::class;
    protected const NOUN = 'element';
}
