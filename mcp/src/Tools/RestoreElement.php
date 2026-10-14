<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Element;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('restore-element')]
#[Title('Restore a soft-deleted shared content element')]
#[Description('Restores a previously soft-deleted shared content element. Returns the restored element as a JSON object.')]
class RestoreElement extends RestoreTool
{
    protected const PERMISSIONS = ['element:keep'];
    protected const MODEL = Element::class;
    protected const NOUN = 'element';
}
