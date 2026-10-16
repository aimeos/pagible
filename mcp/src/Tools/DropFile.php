<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Files;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;


#[Name('drop-file')]
#[Title('Soft-delete a media file')]
#[Description('Soft-deletes a media file. The file can be restored within the retention period. Returns the deleted file as a JSON object.')]
class DropFile extends DropTool
{
    protected const PERMISSIONS = ['file:drop'];
    protected const RESOURCE = Files::class;
    protected const NOUN = 'file';
}
