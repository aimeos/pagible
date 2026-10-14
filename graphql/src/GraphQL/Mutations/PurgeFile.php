<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;


final class PurgeFile extends Lifecycle
{
    protected const MODEL = File::class;
    protected const ACTION = 'purge';
}
