<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Facades;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource\BulkResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;


/**
 * Facade for the shared element operations of Aimeos\Cms\Resource\Elements, which can be replaced by mocks in tests.
 *
 * @phpstan-import-type ElementInput from \Aimeos\Cms\Resource\Elements
 *
 * @method static Element add( ElementInput $input, Authenticatable|null $user = null )
 * @method static Element save( string $id, ElementInput $input, Authenticatable|null $user = null, string|null $latestId = null )
 * @method static BulkResult bulk( array<string> $ids, ElementInput $input, Authenticatable|null $user = null )
 * @method static Element publish( string $id, Authenticatable|null $user = null, string|null $at = null )
 * @method static Collection<int, Element> publishMany( array<string> $ids, Authenticatable|null $user = null, string|null $at = null )
 * @method static Element drop( string $id, Authenticatable|null $user = null )
 * @method static Element purge( string $id, Authenticatable|null $user = null )
 * @method static Element restore( string $id, Authenticatable|null $user = null )
 * @method static Collection<int, Element> dropMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, Element> purgeMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, Element> restoreMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 *
 * @see \Aimeos\Cms\Resource\Elements
 */
class Elements extends Facade
{
    protected static function getFacadeAccessor() : string
    {
        return 'cms.elements';
    }
}
