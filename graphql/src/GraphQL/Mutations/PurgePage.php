<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;


final class PurgePage extends Lifecycle
{
    protected const MODEL = PageView::class;
    protected const ACTION = 'purge';
}
