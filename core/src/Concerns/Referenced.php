<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


/**
 * Relations to the pages and versions referencing the element or file.
 *
 * The pivot tables are named after the PERM constant of the model, e.g.
 * "cms_page_file" and "cms_version_file".
 */
trait Referenced
{
    /**
     * Get the pages the item is referenced by.
     *
     * @return BelongsToMany<PageView, $this> Eloquent relationship to the pages
     */
    public function bypages() : BelongsToMany
    {
        return $this->belongsToMany( PageView::class, 'cms_page_' . static::PERM, static::PERM . '_id', 'variant_id', 'id', 'variant_id' )
            ->allVariants()->select('id', 'path', 'name' );
    }


    /**
     * Get the versions the item is referenced by.
     *
     * @return BelongsToMany<Version, $this> Eloquent relationship to the versions referencing the item
     */
    public function byversions() : BelongsToMany
    {
        return $this->belongsToMany( Version::class, 'cms_version_' . static::PERM )
            ->select('id', 'versionable_id', 'versionable_type', 'published', 'publish_at' );
    }
}
