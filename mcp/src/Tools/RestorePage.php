<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Models\PageView;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('restore-page')]
#[Title('Restore a soft-deleted page')]
#[Description('Restores a previously soft-deleted page with all its language variants. Use restore-page-variant to restore only one deleted language variant. Returns the restored page as a JSON object.')]
class RestorePage extends RestoreTool
{
    protected const PERMISSIONS = ['page:keep'];
    protected const MODEL = PageView::class;
    protected const RESOURCE = Pages::class;
    protected const NOUN = 'page';
}
