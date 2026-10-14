<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\File;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('restore-file')]
#[Title('Restore a soft-deleted media file')]
#[Description('Restores a previously soft-deleted media file. Returns the restored file as a JSON object.')]
class RestoreFile extends RestoreTool
{
    protected const PERMISSIONS = ['file:keep'];
    protected const MODEL = File::class;
    protected const NOUN = 'file';
}
