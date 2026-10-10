<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Query\PageView;
use Illuminate\Database\Migrations\Migration;


/**
 * Creates the read-only page view joining the page tree and all its language variants.
 *
 * The Page model reads from the view and selects the variants by conditions on the view,
 * pages are written to the cms_pages and cms_page_variants tables by the Resource class.
 * Later migrations changing columns used by the view must drop it before and create it again afterwards.
 */
return new class extends Migration
{
    public function down(): void
    {
        PageView::drop();
    }


    public function up(): void
    {
        PageView::create();
    }
};
