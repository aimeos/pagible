<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Jobs\DeleteFilePaths;
use Aimeos\Cms\Utils;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;


/**
 * File model
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $disk
 * @property string $mime
 * @property string|null $lang
 * @property string $name
 * @property string|null $path
 * @property mixed $previews
 * @property \stdClass $description
 * @property \stdClass $transcription
 * @property string $editor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $latest_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Page> $bypages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Element> $byelements
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class File extends Base
{
    public const PERM = 'file';
    protected const PRUNE_SIZE = 100;

    /** @var list<string> Columns for eager-loading file relations */
    public const SELECT_COLUMNS = [
        'cms_files.id', 'cms_files.tenant_id', 'cms_files.latest_id', 'disk', 'name', 'mime', 'path',
        'previews', 'description', 'transcription', 'created_at',
    ];


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
        'disk' => 'public',
        'mime' => '',
        'lang' => null,
        'name' => '',
        'path' => '',
        'previews' => '{}',
        'description' => '{}',
        'transcription' => '{}',
        'editor' => '',
    ];

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'disk' => 'string',
        'name' => 'string',
        'previews' => 'object',
        'description' => 'object',
        'transcription' => 'object',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transcription',
        'description',
        'name',
        'lang',
    ];

    /**
     * The attributes that are return by toArray()
     *
     * @var list<string>
     */
    protected $visible = [
        'disk',
        'lang',
        'name',
        'mime',
        'path',
        'previews',
        'description',
        'transcription',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_files';


    /**
     * Returns the text content of the file.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        $parts = [$this->name ?? ''];

        foreach( (array) $this->description as $lang => $value ) {
            $parts[] = $lang . ":\n" . $value;
        }

        foreach( (array) $this->transcription as $lang => $value ) {
            $parts[] = $lang . ":\n" . $value;
        }

        return trim( implode( "\n", $parts ) );
    }


    /**
     * Adds the uploaded file to the storage and returns the path to it
     *
     * @param UploadedFile $upload File upload
     * @return self The current instance for method chaining
     */
    public function addFile( UploadedFile $upload ) : self
    {
        $this->path = null;

        $disk = $this->storage();
        $dir = $this->dir();

        $name = $this->filename( $upload->getClientOriginalName(), $upload->guessExtension() );
        $path = $dir . '/' . $name;

        if( $upload->getMimeType() === 'image/svg+xml' )
        {
            $content = file_get_contents( $upload->getRealPath() );

            if( !( $content = Utils::cleanSvg( $content ) ) ) {
                $msg = 'Invalid file "%s"';
                throw new \Aimeos\Cms\InvalidException( sprintf( $msg, $upload->getClientOriginalName() ) );
            }

            $stored = $disk->put( $path, $content );
        }
        else
        {
            $stored = $disk->putFileAs( $dir, $upload, $name );
        }

        if( !$stored ) {
            $msg = 'Unable to store file "%s" to "%s"';
            throw new \Aimeos\Cms\Exception( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
        }

        $this->path = $path;
        return $this;
    }


    /**
     * Creates and adds the preview images
     *
     * @param UploadedFile|string $resource File upload or URL to the file
     * @return self The current instance for method chaining
     */
    public function addPreviews( UploadedFile|string $resource ) : self
    {
        $sizes = config( 'cms.image.preview-sizes', [[]] );
        $manager = $this->imageManager();

        if( is_string( $resource ) && Utils::isValidUrl( $resource ) ) {
            $resource = $this->fetchUrl( $resource, $manager->driver() );

            if( !is_resource( $resource ) ) {
                return $this;
            }

            // SVG images can't be rasterized, so store the SVG itself as preview
            if( in_array( $this->mime, ['image/svg+xml', 'application/gzip'] ) ) {
                return $this->addSvgPreview( $resource );
            }
        }

        if( $resource instanceof UploadedFile ) {
            $filename = $resource->getClientOriginalName();
            $mime = (string) $resource->getMimeType();
        } else {
            $filename = $this->name;
            $mime = $this->mime;
        }

        if( !$manager->driver()->supports( $mime ) ) {
            return $this;
        }

        if( is_string( $resource ) ) {
            throw new \Aimeos\Cms\InvalidException( 'Invalid image URL' );
        }

        // previews of the previous image must not remain if creating the new ones fails
        $this->previews = [];
        $this->previews = $this->storePreviews( $resource, $sizes, [], $filename );

        return $this;
    }


    /**
     * Get all (shared) content elements referencing the file.
     *
     * @return BelongsToMany<Element, $this> Eloquent relationship to the element referencing the file
     */
    public function byelements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_element_file' )
            ->select('id', 'type', 'name' );
    }


    /**
     * Get all pages referencing the file.
     *
     * @return BelongsToMany<Page, $this> Eloquent relationship to the pages referencing the file
     */
    public function bypages() : BelongsToMany
    {
        return $this->belongsToMany( Page::class, 'cms_page_file' )
            ->select('id', 'path', 'name' );
    }


    /**
     * Get all versions referencing the file.
     *
     * @return BelongsToMany<Version, $this> Eloquent relationship to the versions referencing the file
     */
    public function byversions() : BelongsToMany
    {
        return $this->belongsToMany( Version::class, 'cms_version_file' )
            ->select('id', 'versionable_id', 'versionable_type', 'published', 'publish_at' );
    }


    /**
     * Rejects raster images whose decoded dimensions exceed the configured limit.
     *
     * @param UploadedFile|resource $resource Uploaded image or downloaded temporary file
     */
    public static function checkPixels( mixed $resource ) : void
    {
        $path = $resource instanceof UploadedFile ? $resource->getRealPath() : null;

        if( is_resource( $resource ) ) {
            $path = stream_get_meta_data( $resource )['uri'] ?? null;
        }

        if( !is_string( $path ) || !( $info = @getimagesize( $path ) ) ) {
            throw new \Aimeos\Cms\InvalidException( 'Invalid image' );
        }

        $max = max( 1, (int) config( 'cms.upload.maxpixels', 4096 * 4096 ) );
        $width = (int) $info[0];
        $height = (int) $info[1];

        if( $height < 1 || $width < 1 || $width > intdiv( $max, $height ) ) {
            throw new \Aimeos\Cms\InvalidException( sprintf( 'Image exceeds the maximum size of %d pixels', $max ) );
        }
    }


    /**
     * Validates a primary or preview upload before storage or image decoding.
     */
    public static function checkUpload( UploadedFile $upload, bool $preview = false ) : void
    {
        $label = $preview ? 'Preview' : 'File';

        if( !$upload->isValid() ) {
            throw new \Aimeos\Cms\InvalidException( sprintf( 'Invalid %s upload', strtolower( $label ) ) );
        }

        if( !Utils::isValidUpload( $upload ) ) {
            throw new \Aimeos\Cms\InvalidException( sprintf( '%s size of %s MB exceeds the maximum of %s MB',
                $label, round( $upload->getSize() / 1024 / 1024, 3 ), config( 'cms.upload.filesize', 50 ) ) );
        }

        $mime = (string) $upload->getMimeType();

        Utils::checkMimetype( $mime, $label, $preview ? 'image/' : '' );
    }


    /**
     * Ensures a client-supplied file path stays within the current tenant's storage or is a system URL.
     *
     * @param string|null $path Storage path or URL provided by the caller
     * @return ($path is null ? null : string) The validated path or null if none was given
     * @throws \Aimeos\Cms\Exception If the path escapes the tenant's storage directory
     */
    public static function checkPath( ?string $path ) : ?string
    {
        if( $path === null ) {
            return null;
        }

        $value = str_starts_with( $path, 'http' )
            ? ( Utils::isValidUrl( $path, false ) ? $path : null )
            : Utils::normalizePath( $path );

        if( $value === null ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid file path "%s"', $path ) );
        }

        return $value;
    }


    /**
     * Ensures newly assigned managed paths belong to the UUID directory of this file.
     *
     * @param array<array-key, mixed> $paths Storage paths or remote URLs
     * @throws \Aimeos\Cms\Exception If a path is outside the UUID directory or a private file uses a remote path
     */
    public function checkPaths( array $paths ) : void
    {
        $tenant = \Aimeos\Cms\Tenancy::value();

        foreach( $paths as $path )
        {
            if( $path === null || $path === '' ) {
                continue;
            }

            if( is_string( $path ) && str_starts_with( $path, 'http' ) )
            {
                if( $this->getAttribute( 'disk' ) === 'private' ) {
                    throw new \Aimeos\Cms\Exception( 'Private files cannot use remote paths' );
                }

                continue;
            }

            if( !self::owns( $tenant, (string) $this->id, $path ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'File path "%s" is outside its UUID directory', (string) $path ) );
            }
        }
    }


    /**
     * Ensures prepared local files still exist on the disk of this file while the ownership lock is held.
     *
     * @param array<array-key, mixed> $paths Prepared storage paths or remote URLs
     * @throws \Aimeos\Cms\Exception If a path is invalid or not available on the storage disk
     */
    public function checkStored( array $paths ) : void
    {
        $storage = $this->storage();

        foreach( $paths as $path )
        {
            if( $path === null ) {
                continue;
            }

            $value = self::checkPath( (string) $path );

            if( !str_starts_with( $value, 'http' ) && !$storage->exists( $value ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Prepared file "%s" is not available', (string) $path ) );
            }
        }
    }


    /**
     * Returns the UUID-owned storage directory for this File.
     */
    public function dir() : string
    {
        $this->setUniqueIds();
        return Utils::prefix( \Aimeos\Cms\Tenancy::value() ) . (string) $this->getAttribute( 'id' );
    }


    /**
     * Returns the configured Laravel storage disk name.
     */
    public static function diskName( string $disk ) : string
    {
        if( !in_array( $disk, ['public', 'private'], true ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid file disk "%s"', $disk ) );
        }

        $name = (string) config( "cms.disks.{$disk}.name", $disk === 'private' ? 'local' : 'public' );

        if( $disk === 'private' && $name === (string) config( 'cms.disks.public.name', 'public' ) ) {
            throw new \Aimeos\Cms\Exception( 'Public and private file disks must be different' );
        }

        return $name;
    }


    /**
     * Returns the File UUID owning a canonical managed path.
     *
     * @param string $tenant Tenant namespace
     * @param mixed $path Candidate storage path
     * @return string|null Owner UUID as stored in the path, or null for invalid, remote, legacy, or foreign paths
     */
    public static function owner( string $tenant, mixed $path ) : ?string
    {
        if( !( $path = Utils::normalizePath( $path, $tenant ) ) ) {
            return null;
        }

        return explode( '/', substr( $path, strlen( Utils::prefix( $tenant ) ) ), 2 )[0];
    }


    /**
     * Tests whether a managed path belongs to a File UUID without changing either representation.
     */
    public static function owns( string $tenant, string $id, mixed $path ) : bool
    {
        $owner = self::owner( $tenant, $path );

        return $owner !== null && strcasecmp( $owner, $id ) === 0;
    }


    /**
     * Fetches a URL as a bounded temporary stream.
     *
     * @param string $url URL to fetch
     * @param DriverInterface|null $driver Image driver for an optional format support check
     * @return resource|null Seekable tmpfile resource or null if not an image
     */
    public function fetchUrl( string $url, ?DriverInterface $driver = null )
    {
        $response = Utils::http( $url, ['stream' => true], ['User-Agent' => 'Pagible/1.0 (+https://pagible.com)'] );

        if( !$response->successful() ) {
            throw new \Aimeos\Cms\InvalidException( sprintf( 'Failed to download "%s"', $url ) );
        }

        $limit = max( 0, (float) config( 'cms.upload.filesize', 50 ) );
        $max = (int) ( $limit * 1024 * 1024 );
        $body = $response->toPsrResponse()->getBody();
        $length = trim( $response->header( 'Content-Length' ) );
        $message = $driver
            ? sprintf( 'Remote file exceeds the maximum size of %s MB', $limit )
            : 'Remote file exceeds the maximum upload size';

        try
        {
            if( $length !== '' && ctype_digit( $length ) && (int) $length > $max ) {
                throw new \Aimeos\Cms\InvalidException( $message );
            }

            $bytes = $body->read( min( 4096, $max + 1 ) );

            if( strlen( $bytes ) > $max ) {
                throw new \Aimeos\Cms\InvalidException( $message );
            }

            $this->mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $bytes ) ?: 'application/octet-stream';

            // SVG (incl. gzip-compressed SVGZ) isn't supported by the image drivers but is stored as preview itself
            if( $driver && !in_array( $this->mime, ['image/svg+xml', 'application/gzip'] )
                && !$driver->supports( $this->mime ) )
            {
                return null;
            }

            if( !( $tmp = tmpfile() ) ) {
                throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
            }

            fwrite( $tmp, $bytes );
            $size = strlen( $bytes );

            while( !$body->eof() )
            {
                $chunk = $body->read( min( 1048576, $max - $size + 1 ) );
                $size += strlen( $chunk );

                if( $size > $max ) {
                    fclose( $tmp );
                    throw new \Aimeos\Cms\InvalidException( $message );
                }

                fwrite( $tmp, $chunk );
            }

            fseek( $tmp, 0 );
            return $tmp;
        }
        finally
        {
            $body->close();
        }
    }


    /**
     * Validates and ingests a new primary file or preview outside the database transaction.
     *
     * Private remote URLs are downloaded into UUID-owned storage. Failed ingestion removes generated previews and any
     * uploaded primary path before rethrowing the original error.
     *
     * @param UploadedFile|string|null $source Uploaded primary file or local/remote path
     * @param UploadedFile|null $preview Uploaded preview or null for automatic previews
     * @return self The ingested file
     * @throws \Aimeos\Cms\Exception If the source, type, size, storage disk, or generated preview is invalid
     */
    public function ingest( UploadedFile|string|null $source = null, ?UploadedFile $preview = null ) : self
    {
        if( $source instanceof UploadedFile ) {
            self::checkUpload( $source );
        } elseif( is_string( $source ) && str_starts_with( $source, 'http' ) && !Utils::isValidUrl( $source ) ) {
            throw new \Aimeos\Cms\InvalidException( sprintf( 'Invalid URL "%s"', $source ) );
        }

        if( $preview ) {
            self::checkUpload( $preview, true );
        }

        $resource = null;
        $started = false;

        try
        {
            if( is_string( $source ) && str_starts_with( $source, 'http' ) && $this->getAttribute( 'disk' ) === 'private' )
            {
                $resource = $this->fetchUrl( $source );

                if( !is_resource( $resource ) ) {
                    throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
                }

                $path = stream_get_meta_data( $resource )['uri'] ?? null;
                $name = basename( (string) parse_url( $source, PHP_URL_PATH ) ) ?: 'file';

                if( !is_string( $path ) ) {
                    throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
                }

                $source = new UploadedFile( $path, $name, null, null, true );
                self::checkUpload( $source );
            }

            $started = true;

            if( $source instanceof UploadedFile ) {
                $this->ingestUpload( $source, $preview );
            } elseif( is_string( $source ) ) {
                $this->ingestPath( $source, $preview );
            } elseif( $preview ) {
                $this->addPreviews( $preview );
            }

            if( $source !== null ) {
                Utils::checkMimetype( (string) $this->mime );
            }

            return $this;
        }
        catch( \Throwable $t )
        {
            if( $started )
            {
                try {
                    $this->removePreviews();

                    if( $source instanceof UploadedFile ) {
                        $this->removeFile();
                    }
                } catch( \Throwable $cleanup ) {
                    report( $cleanup );
                }
            }

            throw $t;
        }
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder instance for pruning
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()
            ->select( 'id', 'tenant_id', 'path', 'previews', 'deleted_at' )
            ->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) )
            // model:prune runs outside the tenant of the file, references of trashed items keep the file too
            ->whereDoesntHave( 'byversions', fn( $q ) => $q->withoutGlobalScopes() )
            ->whereDoesntHave( 'bypages', fn( $q ) => $q->withoutGlobalScopes() )
            ->whereDoesntHave( 'byelements', fn( $q ) => $q->withoutGlobalScopes() );
    }


    /**
     * Removes the unused file and its stored paths while the storage of its tenant is locked.
     *
     * @return bool|null TRUE if the file has been deleted, FALSE if it's in use again
     */
    public function prune() : ?bool
    {
        $tenant = (string) $this->tenant_id;

        return Utils::storageLock( $tenant, fn() => Utils::fileLock( $tenant, (string) $this->id, function() {
            // the file may have been referenced again since it has been selected
            if( !$this->prunable()->withTrashed()->whereKey( $this->id )->exists() ) {
                return false;
            }

            $this->pruning();
            return $this->forceDelete();
        } ) );
    }


    /**
     * Applies a lifecycle action to the locked files and removes the stored files when purging.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Base> $items Files
     * @param 'dropped'|'purged'|'restored' $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    public static function lifecycle( \Illuminate\Database\Eloquent\Collection $items, string $action, string $editor ) : void
    {
        if( $action === 'purged' ) {
            self::purgeMany( \Aimeos\Cms\Tenancy::value(), $items );
        }

        parent::lifecycle( $items, $action, $editor );
    }


    /**
     * Executes the callback while the storage of the tenant is locked.
     *
     * @template T
     * @param \Closure(): T $fcn Callback to execute
     * @return T Result of the callback
     */
    public static function locked( \Closure $fcn ) : mixed
    {
        return Utils::storageLock( \Aimeos\Cms\Tenancy::value(), $fcn );
    }


    /**
     * Deletes all versions and queues storage cleanup for a locked file batch.
     *
     * @param Collection<int, covariant Base> $files
     */
    public static function purgeMany( string $tenant, Collection $files ) : void
    {
        /** @var array<string> $ids */
        $ids = $files->pluck( 'id' )->all();

        do
        {
            $versions = Version::withoutTenancy()->select( 'id', 'data' )
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'versionable_id', $ids )
                ->where( 'versionable_type', File::class )
                ->orderBy( 'id' )->limit( 500 )->get();

            if( !$versions->isEmpty() ) {
                Version::withoutTenancy()->where( 'tenant_id', $tenant )
                    ->whereIn( 'id', $versions->modelKeys() )->delete();
                self::deletePaths( self::paths( $versions->pluck( 'data' ) ), $tenant );
            }
        }
        while( $versions->count() === 500 );

        self::deletePaths( self::paths( $files ), $tenant );
    }


    /**
     * Moves the current and historical managed paths to another logical disk.
     *
     * The caller must hold the tenant storage and file locks.
     *
     * @param string $disk Target logical disk, either "public" or "private"
     * @param string $editor Name of the editor relocating the file
     * @return self The current instance for method chaining
     * @throws \Aimeos\Cms\Exception If a path is remote, foreign, missing or can't be copied and verified
     */
    public function relocate( string $disk, string $editor ) : self
    {
        $paths = $this->relocatable( $disk );
        $source = $this->storage();
        $target = Storage::disk( self::diskName( $disk ) );

        foreach( $paths as $path )
        {
            $sourceExists = $source->exists( $path );

            if( !$sourceExists && !$target->exists( $path ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'File "%s" is missing from both storage disks', $path ) );
            }

            if( $sourceExists )
            {
                $size = $source->size( $path );
                $stream = $source->readStream( $path );

                if( !$stream ) {
                    throw new \Aimeos\Cms\Exception( sprintf( 'Unable to read file "%s"', $path ) );
                }

                try {
                    if( !$target->writeStream( $path, $stream ) ) {
                        throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store file "%s"', $path ) );
                    }
                } finally {
                    if( is_resource( $stream ) ) {
                        fclose( $stream );
                    }
                }

                if( !$target->exists( $path ) || $size !== $target->size( $path ) ) {
                    throw new \Aimeos\Cms\Exception( sprintf( 'Unable to verify file "%s"', $path ) );
                }
            }
        }

        if( !$paths->isEmpty() ) {
            $source->delete( $paths->all() );
        }

        foreach( $paths as $path ) {
            if( $source->exists( $path ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to remove file "%s" from its previous disk', $path ) );
            }
        }

        Utils::transaction( function() use ( $disk, $editor ) {
            /** @var File $locked */
            $locked = self::lockForUpdate()->findOrFail( $this->id );
            $locked->disk = $disk;
            $locked->editor = $editor;
            self::withoutSyncingToSearch( fn() => $locked->save() );
        } );

        $this->disk = $disk;
        $this->editor = $editor;

        if( $disk === 'private' && !$paths->isEmpty() ) {
            FilesRemoved::dispatch( (string) $this->tenant_id, array_values( $paths->all() ) );
        }

        return $this;
    }


    /**
     * Removes the file from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removeFile() : self
    {
        if( $this->path && !str_starts_with( $this->path, 'http' ) ) {
            $this->erase( [$this->path] );
        }

        $this->path = null;
        return $this;
    }


    /**
     * Removes all preview images from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removePreviews() : self
    {
        $previews = array_values( (array) $this->previews );

        if( !empty( $previews ) ) {
            $this->erase( $previews );
        }

        $this->previews = [];
        return $this;
    }


    /**
     * Splits file version fields into indexed data and auxiliary text.
     *
     * @param array<string, mixed> $data File version fields
     * @return array{data: array<string, mixed>, aux: array<string, mixed>}
     */
    public static function snapshot( array $data ) : array
    {
        $aux = array_flip( ['description', 'transcription'] );
        $skip = $aux + ['disk' => true];

        return [
            'data' => array_diff_key( $data, $skip ),
            'aux' => array_intersect_key( $data, $aux ),
        ];
    }


    /**
     * Tests if previews can be generated from the file itself.
     *
     * @return bool TRUE if the file is an image supported by the image driver, FALSE if not
     */
    public function previewable() : bool
    {
        return (string) $this->path !== '' && $this->imageManager()->driver()->supports( (string) $this->mime );
    }


    /**
     * Compares the previews with the configured sizes without generating images.
     *
     * The configured size of a preview is taken from its file name. Previews of files which
     * aren't supported images as well as remote and SVG previews are never changed.
     *
     * @param array<int|string, string> $previews Existing previews as width/path pairs
     * @param bool $force Replace all previews
     * @return array{keep: array<int, string>, missing: array<string, array<string, mixed>>}
     *  Previews to keep and missing sizes by name
     */
    public function diffPreviews( array $previews, bool $force = false ) : array
    {
        $config = [];
        $keep = [];

        foreach( config( 'cms.image.preview-sizes', [[]] ) as $size ) {
            $config[self::sizeName( $size )] = $size;
        }

        $missing = $config;
        $all = [];

        foreach( $previews as $width => $path ) {
            $all[(int) $width] = (string) $path;
        }

        if( !$this->previewable() ) {
            return ['keep' => $all, 'missing' => []];
        }

        foreach( $all as $width => $path )
        {
            if( str_starts_with( $path, 'http' ) || strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) === 'svg' ) {
                return ['keep' => $all, 'missing' => []];
            }

            // previews without the sizes in their file names are replaced
            if( ( $names = array_flip( self::previewSizes( $path ) ) ) && array_intersect_key( $config, $names ) ) {
                $missing = array_diff_key( $missing, $names );
                $keep[$width] = $path;
            }
        }

        if( $force ) {
            return ['keep' => [], 'missing' => $config];
        }

        return ['keep' => $keep, 'missing' => $missing];
    }


    /**
     * Adds previews for missing configured sizes and leaves out previews of sizes no longer configured.
     *
     * Missing previews are generated from the file itself if it's a supported image. Removed
     * previews are not deleted from the storage.
     *
     * @param array<int|string, string> $previews Existing previews as width/path pairs
     * @param array{keep: array<int, string>, missing: array<string, array<string, mixed>>}|null $diff
     *  Result of diffPreviews() for these previews if already available
     * @return array<int, string>|null New previews as width/path pairs or NULL if nothing changed
     * @throws \Aimeos\Cms\Exception If the source image is not available or invalid
     * @see diffPreviews()
     */
    public function syncPreviews( array $previews, ?array $diff = null ) : ?array
    {
        ['keep' => $map, 'missing' => $sizes] = $diff ?? $this->diffPreviews( $previews );

        if( $sizes && ( $resource = $this->previewSource( $this->imageManager()->driver() ) ) )
        {
            // all sizes are required to find the ones resulting in the same width as the missing sizes
            try {
                $map = $this->storePreviews( $resource, config( 'cms.image.preview-sizes', [[]] ), $map, $this->name ?: 'image' );
            } finally {
                fclose( $resource );
            }
        }

        ksort( $map );
        $old = array_map( 'strval', $previews );
        ksort( $old );

        return $map === $old ? null : $map;
    }


    /**
     * Returns the searchable data for the file.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $attrs = ['name', 'mime', 'description', 'transcription', 'deleted_at', 'latest_id'];

        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        $version = $this->latest;

        return [
            'content' => $this->trashed() ? '' : mb_strtolower( (string) $this ),
            'draft' => mb_strtolower( (string) $version ),
            'tenant_id' => $this->tenant_id ?? '',
            'lang' => $version?->lang,
            'editor' => $version->editor ?? '',
            'mime' => $version?->data->mime ?? '',
            'published' => (bool) ( $version->published ?? false ),
            'scheduled' => (int) ( $version?->data->scheduled ?? 0 ),
        ];
    }


    /**
     * Interact with the "name" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "name" property
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "description" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "description" property
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ),
        );
    }


    /**
     * Stores a downloaded SVG image locally and uses it as the preview.
     *
     * SVG images can't be rasterized into webp/jpg previews, so the sanitized
     * SVG itself is stored on the disk and referenced as the preview at the
     * largest configured preview width. Gzip-compressed SVGZ content is
     * decompressed so the stored preview is a plain, browser-renderable SVG.
     * Returns without a preview if the content isn't a valid SVG.
     *
     * @param resource $resource Seekable file pointer to the downloaded SVG content
     * @return self The current instance for method chaining
     */
    protected function addSvgPreview( $resource ) : self
    {
        $raw = (string) stream_get_contents( $resource );

        // decompress SVGZ so the stored preview is a plain, browser-renderable SVG
        if( !( $content = Utils::cleanSvg( Utils::gunzip( $raw ) ) ) ) {
            return $this;
        }

        $disk = $this->storage();
        $dir = $this->dir();
        $path = $dir . '/' . $this->filename( $this->name ?: 'image.svg', 'svg' );

        if( !$disk->put( $path, $content ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store preview "%s"', $path ) );
        }

        $widths = array_filter( array_column( config( 'cms.image.preview-sizes', [[]] ), 'width' ) );

        $this->mime = 'image/svg+xml';
        $this->previews = [( $widths ? max( $widths ) : 1920 ) => $path];
        return $this;
    }


    /**
     * Returns the new name for the uploaded file
     *
     * @param string $filename Name of the file
     * @param string|null $ext File extension to use, if not given, the original file extension is used
     * @param string $size Names of the preview sizes, if used
     * @return string New file name
     */
    protected function filename( string $filename, ?string $ext = null, string $size = '' ) : string
    {
        $regex = '/([[:cntrl:]]|[[:blank:]]|\/|\.)+/smu';

        $ext = Utils::extension( $ext ?: pathinfo( $filename, PATHINFO_EXTENSION ) );
        $name = preg_replace( $regex, '', pathinfo( $filename, PATHINFO_FILENAME ) );

        $hash = strtr( base64_encode( random_bytes( 3 ) ), '+/', '-_' );

        return $name . '_' . $size . '_' . $hash . '.' . $ext;
    }


    /**
     * Returns the image manager for the configured image driver.
     */
    protected function imageManager() : ImageManager
    {
        return ImageManager::withDriver( '\\Intervention\\Image\\Drivers\\' . ucFirst( config( 'cms.image.driver', 'gd' ) ) . '\Driver' );
    }


    /**
     * Assigns a public URL or existing managed path and creates requested previews.
     *
     * @param string $source Public URL or managed storage path
     * @param UploadedFile|null $preview Explicit preview upload, or null for remote automatic previews
     */
    protected function ingestPath( string $source, ?UploadedFile $preview ) : void
    {
        $this->path = $source;
        $this->name = $this->name ?: ( str_starts_with( $source, 'http' )
            ? substr( $source, 0, 255 )
            : pathinfo( $source, PATHINFO_BASENAME ) );

        if( $preview ) {
            $this->addPreviews( $preview );
        } elseif( str_starts_with( $source, 'http' ) ) {
            $this->addPreviews( $source );
        }

        $this->mime = $this->mime ?: Utils::mimetype( $source, self::diskName( (string) $this->disk ) );
    }


    /**
     * Stores an uploaded file and creates its previews.
     *
     * @param UploadedFile $source Validated primary upload
     * @param UploadedFile|null $preview Explicit preview upload, or null to derive previews from images
     */
    protected function ingestUpload( UploadedFile $source, ?UploadedFile $preview ) : void
    {
        $this->addFile( $source );
        $this->mime = Utils::mimetype( (string) $this->path, self::diskName( (string) $this->disk ) );
        $this->name = $this->name ?: pathinfo( $source->getClientOriginalName(), PATHINFO_BASENAME );

        if( $preview || str_starts_with( (string) $source->getMimeType(), 'image/' ) ) {
            $this->addPreviews( $preview ?? $source );
        }
    }


    /**
     * Returns the image previews can be generated from as temporary file.
     *
     * @param DriverInterface $driver Image driver for the format support check
     * @return resource|null Seekable temporary file or NULL if the file is no supported image
     */
    protected function previewSource( DriverInterface $driver )
    {
        $path = (string) $this->path;

        if( $path === '' || !$driver->supports( (string) $this->mime ) ) {
            return null;
        }

        if( str_starts_with( $path, 'http' ) ) {
            return Utils::isValidUrl( $path ) ? $this->fetchUrl( $path, $driver ) : null;
        }

        $disk = $this->storage();

        if( !( $stream = $disk->readStream( $path ) ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Unable to read file "%s"', $path ) );
        }

        try
        {
            if( !( $tmp = tmpfile() ) ) {
                throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
            }

            stream_copy_to_stream( $stream, $tmp );
            fseek( $tmp, 0 );

            return $tmp;
        }
        finally
        {
            fclose( $stream );
        }
    }


    /**
     * Returns the unique current and historical managed paths which can be relocated.
     *
     * @param string $disk Target logical disk
     * @return Collection<int, non-empty-string> Local storage paths
     * @throws \Aimeos\Cms\Exception If a path is remote where unsupported or does not belong to the file UUID
     */
    protected function relocatable( string $disk ) : Collection
    {
        $paths = self::paths( collect( [$this] )->concat( Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', self::class )
            ->select( 'data' )->cursor()->pluck( 'data' ) ) );

        if( $disk === 'private' && $paths->contains( fn( $path ) => str_starts_with( $path, 'http' ) ) ) {
            throw new \Aimeos\Cms\Exception( 'Remote files cannot be relocated' );
        }

        $paths = $paths->reject( fn( $path ) => str_starts_with( $path, 'http' ) )->values();
        $this->checkPaths( $paths->all() );

        return $paths;
    }


    /**
     * Returns the storage disk of the file.
     */
    protected function storage() : \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk( self::diskName( (string) $this->disk ) );
    }


    /**
     * Stores previews of the image for the sizes not contained in the file names of the existing previews.
     *
     * Sizes resulting in the same width, e.g. because the image is smaller, share one preview
     * whose file name contains all of them. An existing preview of that width is replaced if
     * its file name doesn't contain all of them.
     *
     * @param UploadedFile|resource $resource Uploaded image or image file handle
     * @param iterable<array<string, mixed>> $sizes Maximum preview widths and heights
     * @param array<int, string> $map Existing previews as width/path pairs
     * @param string $filename Name of the original file
     * @return array<int, string> Existing and new previews as width/path pairs
     */
    protected function storePreviews( mixed $resource, iterable $sizes, array $map, string $filename ) : array
    {
        self::checkPixels( $resource );

        $image = $this->imageManager()->read( $resource );
        $existing = array_merge( ...array_map( self::previewSizes( ... ), array_values( $map ) ) );
        $targets = [];
        $created = [];

        foreach( $sizes as $size )
        {
            $target = $image->size()->scaleDown( $size['width'] ?? null, $size['height'] ?? null );
            $targets[$target->width()][0] ??= $target;
            $targets[$target->width()][1][] = self::sizeName( $size );
        }

        krsort( $targets );

        try
        {
            foreach( $targets as $width => [$target, $names] )
            {
                if( !array_diff( $names, $existing ) ) {
                    continue;
                }

                // each preview is scaled down from the next larger one, so the full image is scaled only once
                if( $image->width() !== $target->width() || $image->height() !== $target->height() ) {
                    $image->resize( $target->width(), $target->height() );
                }

                $map[$width] = $created[] = $this->storePreview( $image, implode( '-', $names ), $filename );
            }
        }
        catch( \Throwable $t )
        {
            $this->storage()->delete( $created );
            throw $t;
        }

        ksort( $map );
        return $map;
    }


    /**
     * Returns the name of the preview size used in the preview file names.
     *
     * @param array<string, mixed> $size Maximum preview width and height
     * @return string Width, height if no width is configured or empty string
     */
    protected static function sizeName( array $size ) : string
    {
        return (string) ( $size['width'] ?? $size['height'] ?? '' );
    }


    /**
     * Returns the names of the preview sizes contained in the preview file name.
     *
     * @param string $path Preview path
     * @return list<string> Names of the preview sizes or empty if the file name contains none
     */
    protected static function previewSizes( string $path ) : array
    {
        if( preg_match( '/_([\d-]*)_[A-Za-z0-9_-]{4}\.[A-Za-z0-9]+$/', $path, $match ) !== 1 ) {
            return [];
        }

        return explode( '-', $match[1] );
    }


    /**
     * Stores the image as preview.
     *
     * @param ImageInterface $image Image scaled down to the preview size
     * @param string $size Names of the configured preview sizes used in the file name
     * @param string $filename Name of the original file
     * @return string Path of the stored preview
     */
    protected function storePreview( ImageInterface $image, string $size, string $filename ) : string
    {
        $ext = $this->imageManager()->driver()->supports( 'image/webp' ) ? 'webp' : 'jpg';
        $disk = $this->storage();

        $ptr = $image->encodeByExtension( $ext, quality: (int) config( 'cms.image.quality', 75 ) )->toFilePointer();
        $path = $this->dir() . '/' . $this->filename( $filename, $ext, $size );

        if( !$disk->put( $path, $ptr ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store preview "%s"', $path ) );
        }

        return $path;
    }


    /**
     * Deletes storage paths after the surrounding database transaction commits.
     *
     * @param Collection<array-key, mixed> $paths
     * @param string $tenant Tenant ID owning the storage namespace
     */
    protected static function deletePaths( Collection $paths, string $tenant ) : void
    {
        $paths = $paths->filter( fn( $path ) => $path && !str_starts_with( (string) $path, 'http' ) )
            ->map( strval(...) )->unique()->values();

        if( $paths->isEmpty() ) {
            return;
        }

        foreach( $paths->chunk( 100 ) as $chunk ) {
            DB::afterCommit( fn() => Utils::deferStorage( $tenant,
                fn() => DeleteFilePaths::dispatch( $tenant, $chunk->all() ),
            ) );
        }
    }


    /**
     * Returns the unique storage paths and URLs of files or file version data.
     *
     * @param iterable<mixed> $items Files or file version data objects
     * @return Collection<int, non-empty-string> Paths and URLs of the files and their previews
     */
    protected static function paths( iterable $items ) : Collection
    {
        return collect( $items )
            ->flatMap( fn( $item ) => [$item->path ?? null, ...(array) ( $item->previews ?? [] )] )
            ->filter( fn( $path ) => is_string( $path ) && $path !== '' )
            ->unique()->values();
    }


    /**
     * Returns the callback removing a chunk of stale versions and their stored files no longer used.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids File IDs
     * @param array<string> $keep IDs of the versions which are kept
     * @return \Closure(array<string>): void
     */
    protected static function pruner( string $tenant, array $ids, array $keep ) : \Closure
    {
        $paths = self::paths( Version::withoutTenancy()->where( 'tenant_id', $tenant )
            ->whereIn( 'id', $keep )->get( ['id', 'data'] )->pluck( 'data' )
            ->concat( File::withoutTenancy()->withTrashed()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $ids )->get( ['id', 'path', 'previews'] ) ) );

        return function( array $chunk ) use ( $paths, $tenant )
        {
            $drop = Version::withoutTenancy()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $chunk )->get( ['id', 'data'] );

            if( $drop->isEmpty() ) {
                return;
            }

            Version::withoutTenancy()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $drop->modelKeys() )->forceDelete();
            self::deletePaths( self::paths( $drop->pluck( 'data' ) )->diff( $paths ), $tenant );
        };
    }


    /**
     * Prepare the model for pruning.
     */
    protected function pruning() : void
    {
        self::purgeMany( (string) $this->tenant_id, $this->newCollection( [$this] ) );
    }


    /**
     * Interact with the "transcription" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "transcription" property
     */
    protected function transcription(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ),
        );
    }


    /**
     * Returns file-specific publication values.
     *
     * @return array<string, mixed>
     */
    protected function values( Version $version ) : array
    {
        return [
            ...array_intersect_key( (array) $version->aux, array_flip( $this->getFillable() ) ),
            'previews' => (array) $version->data->previews,
            'path' => $version->data->path,
            'mime' => $version->data->mime,
        ];
    }


    /**
     * Deletes the given storage paths of this file immediately.
     *
     * @param array<int, string> $paths Storage paths
     */
    private function erase( array $paths ) : void
    {
        ( new DeleteFilePaths( $this->exists ? (string) $this->tenant_id : \Aimeos\Cms\Tenancy::value(), $paths ) )->handle();
    }
}
