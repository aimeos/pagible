<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Facades;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Acting;
use Aimeos\Cms\Resource\BulkResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;


/**
 * Facade for the page variant operations of Aimeos\Cms\Resource\Variants.
 *
 * Resolves the resource instance from the container, so overrides bound by the application
 * and mocks in tests are used everywhere.
 *
 * @phpstan-import-type PageInput from \Aimeos\Cms\Resource\Pages
 *
 * @method static PageView add( string $id, string $lang, Authenticatable|null $user = null )
 * @method static PageView save( string $id, string $lang, PageInput $input, Authenticatable|null $user = null, string|null $latestId = null )
 * @method static PageView revert( string $id, string $lang, PageInput $input, Authenticatable|null $user = null, string|null $latestId = null )
 * @method static BulkResult bulk( array<string> $ids, string $lang, PageInput $input, Authenticatable|null $user = null, bool $descendants = false )
 * @method static PageView publish( string $id, string $lang, Authenticatable|null $user = null, string|null $at = null )
 * @method static Collection<int, PageView> publishMany( array<string> $ids, string $lang, Authenticatable|null $user = null, string|null $at = null )
 * @method static PageView drop( string $id, string $lang, Authenticatable|null $user = null )
 * @method static PageView restore( string $id, string $lang, Authenticatable|null $user = null )
 * @method static PageView purge( string $id, string $lang, Authenticatable|null $user = null )
 * @method static PageView setSource( string $id, string $lang, Authenticatable|null $user = null )
 * @method static PageView ignore( string $id, string $lang, Authenticatable|null $user = null )
 * @method static Collection<int, PageView> ignoreMany( array<string> $ids, string $lang, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static PageView translate( string $id, string $lang, Authenticatable|null $user = null, (callable(array<int, string>, string, ?string, string): array<int, string>)|null $translate = null )
 * @method static Collection<int, PageView> dropMany( array<string> $ids, string $lang, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, PageView> restoreMany( array<string> $ids, string $lang, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, PageView> purgeMany( array<string> $ids, string $lang, Authenticatable|null $user = null, array<string> $fields = [] )
 *
 * @see \Aimeos\Cms\Resource\Variants
 */
class Variants extends Facade
{
    /**
     * Calls the variants methods for the given user, e.g. Variants::as( $user )->publish( $id ).
     *
     * @param Authenticatable|null $user User the methods are called for, NULL for anonymous calls
     * @return Acting<\Aimeos\Cms\Resource\Variants> Resource methods without the user argument
     */
    public static function as( ?Authenticatable $user ) : Acting
    {
        /** @var \Aimeos\Cms\Resource\Variants $resource */
        $resource = static::getFacadeRoot();
        return new Acting( $resource, $user );
    }


    protected static function getFacadeAccessor() : string
    {
        return \Aimeos\Cms\Resource\Variants::class;
    }
}
