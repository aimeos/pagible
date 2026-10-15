<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Facades;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\BulkResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;


/**
 * Facade for the page operations of Aimeos\Cms\Resource\Pages, which can be replaced by mocks in tests.
 *
 * @phpstan-import-type PageInput from \Aimeos\Cms\Resource\Pages
 *
 * @method static PageView add( PageInput $input, Authenticatable|null $user = null, string|null $ref = null, string|null $parent = null )
 * @method static PageView move( string $id, Authenticatable|null $user = null, string|null $ref = null, string|null $parent = null )
 * @method static PageView save( string $id, PageInput $input, Authenticatable|null $user = null, string|null $latestId = null )
 * @method static BulkResult bulk( array<string> $ids, PageInput $input, Authenticatable|null $user = null, bool $descendants = false )
 * @method static PageView copy( string $id, Authenticatable|null $user = null, string|null $ref = null, string|null $parent = null )
 * @method static PageView publish( string $id, Authenticatable|null $user = null, string|null $at = null )
 * @method static Collection<int, PageView> publishMany( array<string> $ids, Authenticatable|null $user = null, string|null $at = null )
 * @method static PageView drop( string $id, Authenticatable|null $user = null )
 * @method static PageView purge( string $id, Authenticatable|null $user = null )
 * @method static PageView restore( string $id, Authenticatable|null $user = null )
 * @method static Collection<int, PageView> dropMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, PageView> purgeMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, PageView> restoreMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 *
 * @see \Aimeos\Cms\Resource\Pages
 */
class Pages extends Facade
{
    protected static function getFacadeAccessor() : string
    {
        return 'cms.pages';
    }
}
