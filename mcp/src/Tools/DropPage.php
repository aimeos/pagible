<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Pages;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('drop-page')]
#[Title('Soft-delete a page')]
#[Description('Soft-deletes a page with all its language variants. Use drop-page-variant to delete only one language variant. The page can be restored within the retention period using restore-page. Returns the deleted page as a JSON object.')]
class DropPage extends DropTool
{
    protected const PERMISSIONS = ['page:drop'];
    protected const RESOURCE = Pages::class;
    protected const NOUN = 'page';
}
