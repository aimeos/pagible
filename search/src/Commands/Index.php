<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class Index extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:index';

    /**
     * Command description
     */
    protected $description = 'Updates the search index';


    /**
     * Execute command
     */
    public function handle(): void
    {
        foreach( [Page::class, Element::class, File::class] as $model ) {
            $model::makeAllSearchableQuery()->withoutGlobalScope( SoftDeletingScope::class )->searchable( 50 ); // @phpstan-ignore method.notFound
        }
    }
}
