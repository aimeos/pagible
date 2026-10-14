<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;


final class PubPage extends Publish
{
    protected const MODEL = PageView::class;
}
