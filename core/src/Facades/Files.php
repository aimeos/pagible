<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Facades;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource\BulkResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;


/**
 * Facade for the file operations of Aimeos\Cms\Resource\Files, which can be replaced by mocks in tests.
 *
 * @phpstan-import-type FileInput from \Aimeos\Cms\Resource\Files
 *
 * @method static File add( UploadedFile|string $source, FileInput $input = [], Authenticatable|null $user = null, string|null $disk = null, UploadedFile|null $preview = null )
 * @method static File relocate( string $id, string $disk, Authenticatable|null $user = null )
 * @method static Collection<int, File> relocateMany( array<string> $ids, string $disk, Authenticatable|null $user = null )
 * @method static File save( string $id, FileInput $input, Authenticatable|null $user = null, string|null $latestId = null, UploadedFile|null $upload = null, UploadedFile|false|null $preview = null )
 * @method static BulkResult bulk( array<string> $ids, FileInput $input, Authenticatable|null $user = null )
 * @method static File publish( string $id, Authenticatable|null $user = null, string|null $at = null )
 * @method static Collection<int, File> publishMany( array<string> $ids, Authenticatable|null $user = null, string|null $at = null )
 * @method static File drop( string $id, Authenticatable|null $user = null )
 * @method static File purge( string $id, Authenticatable|null $user = null )
 * @method static File restore( string $id, Authenticatable|null $user = null )
 * @method static Collection<int, File> dropMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, File> purgeMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 * @method static Collection<int, File> restoreMany( array<string> $ids, Authenticatable|null $user = null, array<string> $fields = [] )
 *
 * @see \Aimeos\Cms\Resource\Files
 */
class Files extends Facade
{
    protected static function getFacadeAccessor() : string
    {
        return 'cms.files';
    }
}
