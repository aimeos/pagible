<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;


final class PubElement extends Publish
{
    protected const MODEL = Element::class;
}
