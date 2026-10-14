<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;


final class PurgeElement extends Lifecycle
{
    protected const MODEL = Element::class;
    protected const ACTION = 'purge';
}
