<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;


final class DropFile extends Lifecycle
{
    protected const MODEL = File::class;
    protected const ACTION = 'drop';
}
