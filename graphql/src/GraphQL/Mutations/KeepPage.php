<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;


final class KeepPage extends Lifecycle
{
    protected const MODEL = PageView::class;
    protected const ACTION = 'restore';
}
