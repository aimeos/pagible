<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

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
    /** @var list<string> Columns for eager-loading file relations */
    public const SELECT_COLUMNS = [
        'cms_files.id', 'cms_files.tenant_id', 'cms_files.latest_id', 'disk', 'name', 'mime', 'path',
        'previews', 'description', 'transcription',
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

        if( !$upload->isValid() ) {
            throw new \Aimeos\Cms\Exception( 'Invalid file upload' );
        }

        $disk = Storage::disk( self::diskName( (string) $this->getAttribute( 'disk' ) ) );
        $dir = $this->dir();

        $name = $this->filename( $upload->getClientOriginalName(), $upload->guessExtension() );
        $path = $dir . '/' . $name;

        if( $upload->getMimeType() === 'image/svg+xml' )
        {
            $content = file_get_contents( $upload->getRealPath() );

            if( !( $content = Utils::cleanSvg( $content ) ) ) {
                $msg = 'Invalid file "%s"';
                throw new \Aimeos\Cms\Exception( sprintf( $msg, $upload->getClientOriginalName() ) );
            }

            if( !$disk->put( $path, $content ) ) {
                $msg = 'Unable to store file "%s" to "%s"';
                throw new \Aimeos\Cms\Exception( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
            }
        }
        else
        {
            if( !$disk->putFileAs( $dir, $upload, $name ) ) {
                $msg = 'Unable to store file "%s" to "%s"';
                throw new \Aimeos\Cms\Exception( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
            }
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
        $disk = Storage::disk( self::diskName( (string) $this->getAttribute( 'disk' ) ) );
        $dir = $this->dir();

        /** @var ImageManager $manager */
        $manager = ImageManager::withDriver( '\\Intervention\\Image\\Drivers\\' . ucFirst( config( 'cms.image.driver', 'gd' ) ) . '\Driver' );
        $ext = $manager->driver()->supports( 'image/webp' ) ? 'webp' : 'jpg';

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
            throw new \Aimeos\Cms\Exception( 'Invalid image URL' );
        }

        $this->checkPixels( $resource );

        $file = $manager->read( $resource );

        $this->previews = [];
        $map = [];

        try
        {
            foreach( $sizes as $size )
            {
                $image = ( clone $file )->scaleDown( $size['width'] ?? null, $size['height'] ?? null );
                $ptr = $image->encodeByExtension( $ext, quality: 90 )->toFilePointer();
                $path = $dir . '/' . $this->filename( $filename, $ext, $size );

                if( !$disk->put( $path, $ptr ) ) {
                    throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store preview "%s"', $path ) );
                }

                $map[$image->width()] = $path;
                unset( $image, $ptr );
            }
        }
        catch( \Throwable $t )
        {
            $disk->delete( array_values( $map ) );
            throw $t;
        }

        $this->previews = $map;
        unset( $file );

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
     * Returns the UUID-owned storage directory for this File.
     */
    public function dir() : string
    {
        $this->setUniqueIds();
        $tenant = \Aimeos\Cms\Tenancy::value();
        $base = $tenant === '' ? 'cms' : 'cms/' . $tenant;

        return $base . '/' . strtolower( (string) $this->getAttribute( 'id' ) );
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
     * @return string|null Lowercase owner UUID, or null for invalid, remote, legacy, or foreign paths
     */
    public static function owner( string $tenant, mixed $path ) : ?string
    {
        if( !( $path = Utils::normalizePath( $path, $tenant ) ) ) {
            return null;
        }

        $base = $tenant === '' ? 'cms/' : 'cms/' . $tenant . '/';
        return strtolower( explode( '/', substr( $path, strlen( $base ) ), 2 )[0] );
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
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid URL "%s"', $source ) );
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

            if( $source !== null && !Utils::isValidMimetype( (string) $this->mime ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'File type "%s" not allowed, permitted types: %s',
                    $this->mime, implode( ', ', config( 'cms.upload.mimetypes', [] ) ) ) );
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
            ->doesntHave( 'versions' )->doesntHave( 'bypages' )->doesntHave( 'byelements' );
    }


    /**
     * Removes old versions for several files using one ranked version stream.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids File IDs
     */
    public static function pruneVersions( string $tenant, array $ids ) : void
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids ) {
            return;
        }

        $num = max( 0, (int) config( 'cms.versions', 10 ) );
        $dropIds = new \SplTempFileObject( 1024 * 1024 );
        $dropIds->setFlags( \SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY );
        $dropIds->setCsvControl( ',', '"', '' );
        $keepIds = [];
        $stale = false;

        foreach( static::versionRanks( $tenant, $ids ) as [$id, $rank] )
        {
            if( $rank > $num ) {
                $dropIds->fputcsv( [$id], ',', '"', '' );
                $stale = true;
            } else {
                $keepIds[] = $id;
            }
        }

        if( !$stale ) {
            return;
        }

        $keep = self::versionPaths( Version::withoutTenancy()->where( 'tenant_id', $tenant )
            ->whereIn( 'id', $keepIds )->get( ['id', 'data'] ) );

        foreach( File::withoutTenancy()->withTrashed()->where( 'tenant_id', $tenant )
            ->whereIn( 'id', $ids )->get( ['id', 'path', 'previews'] ) as $file )
        {
            if( $file->path ) {
                $keep->push( $file->path );
            }
            foreach( (array) $file->previews as $path ) {
                if( is_string( $path ) && $path !== '' ) {
                    $keep->push( $path );
                }
            }
        }

        $keep = $keep->unique();
        $dropIds->rewind();
        $chunk = [];

        $prune = function( array $ids ) use ( $keep, $tenant )
        {
            $drop = Version::withoutTenancy()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $ids )->get( ['id', 'data'] );

            if( $drop->isEmpty() ) {
                return;
            }

            Version::withoutTenancy()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $drop->modelKeys() )->forceDelete();
            self::deletePaths( self::versionPaths( $drop )->diff( $keep ), $tenant );
        };

        foreach( $dropIds as $row )
        {
            if( !$row ) {
                continue;
            }

            $chunk[] = $row[0];

            if( count( $chunk ) === 100 ) {
                $prune( $chunk );
                $chunk = [];
            }
        }

        if( $chunk ) {
            $prune( $chunk );
        }
    }


    /**
     * Permanently delete the file and all of its versions incl. the stored files.
     *
     * @return void
     */
    public function purge() : void
    {
        $this->pruning();
        $this->forceDelete();
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
                self::deletePaths( self::versionPaths( $versions ), $tenant );
            }
        }
        while( $versions->count() === 500 );

        self::deletePaths( $files->flatMap(
            fn( Base $file ) => [
                $file->getAttribute( 'path' ),
                ...(array) $file->getAttribute( 'previews' ),
            ],
        ), $tenant );
    }


    /**
     * Removes the file from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removeFile() : self
    {
        if( $this->path && !str_starts_with( $this->path, 'http' ) ) {
            ( new DeleteFilePaths(
                $this->exists ? (string) $this->tenant_id : \Aimeos\Cms\Tenancy::value(),
                [$this->path],
            ) )->handle();
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
            ( new DeleteFilePaths(
                $this->exists ? (string) $this->tenant_id : \Aimeos\Cms\Tenancy::value(),
                $previews,
            ) )->handle();
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
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function makeAllSearchableUsing( $query )
    {
        return $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor', 'published' )] );
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
        if( str_starts_with( $raw, "\x1f\x8b" ) )
        {
            $max = max( 0, (int) ( (float) config( 'cms.upload.filesize', 50 ) * 1024 * 1024 ) );
            $content = @gzdecode( $raw, $max + 1 );

            if( $content === false || strlen( $content ) > $max ) {
                throw new \Aimeos\Cms\Exception( 'Decompressed SVG exceeds the maximum upload size' );
            }

            $raw = $content;
        }

        if( !( $content = Utils::cleanSvg( $raw ) ) ) {
            return $this;
        }

        $disk = Storage::disk( self::diskName( (string) $this->getAttribute( 'disk' ) ) );
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
     * Rejects raster images whose decoded dimensions exceed the configured limit.
     *
     * @param UploadedFile|resource $resource Uploaded image or downloaded temporary file
     */
    protected function checkPixels( mixed $resource ) : void
    {
        $path = $resource instanceof UploadedFile ? $resource->getRealPath() : null;

        if( is_resource( $resource ) ) {
            $path = stream_get_meta_data( $resource )['uri'] ?? null;
        }

        if( !is_string( $path ) || !( $info = @getimagesize( $path ) ) ) {
            throw new \Aimeos\Cms\Exception( 'Invalid image' );
        }

        $max = max( 1, (int) config( 'cms.upload.maxpixels', 4096 * 4096 ) );
        $width = (int) $info[0];
        $height = (int) $info[1];

        if( $height < 1 || $width < 1 || $width > intdiv( $max, $height ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Image exceeds the maximum size of %d pixels', $max ) );
        }
    }


    /**
     * Validates a primary or preview upload before storage or image decoding.
     */
    protected static function checkUpload( UploadedFile $upload, bool $preview = false ) : void
    {
        $label = $preview ? 'Preview' : 'File';

        if( !$upload->isValid() ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid %s upload', strtolower( $label ) ) );
        }

        if( !Utils::isValidUpload( $upload ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( '%s size of %s MB exceeds the maximum of %s MB',
                $label, round( $upload->getSize() / 1024 / 1024, 3 ), config( 'cms.upload.filesize', 50 ) ) );
        }

        $mime = (string) $upload->getMimeType();

        if( ( $preview && !str_starts_with( $mime, 'image/' ) ) || !Utils::isValidMimetype( $mime ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( '%s type "%s" not allowed, permitted types: %s',
                $label, $mime, implode( ', ', config( 'cms.upload.mimetypes', [] ) ) ) );
        }
    }


    /**
     * Fetches a URL as a bounded temporary stream.
     *
     * @param string $url URL to fetch
     * @param DriverInterface|null $driver Image driver for an optional format support check
     * @return resource|null Seekable tmpfile resource or null if not an image
     */
    protected function fetchUrl( string $url, ?DriverInterface $driver = null )
    {
        $response = Utils::http( $url, ['stream' => true] );

        if( !$response->successful() ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Failed to download "%s"', $url ) );
        }

        $limit = max( 0, (float) config( 'cms.upload.filesize', 50 ) );
        $max = (int) ( $limit * 1024 * 1024 );
        $body = $response->toPsrResponse()->getBody();
        $length = trim( $response->header( 'Content-Length' ) );
        $message = $driver
            ? sprintf( 'Remote file exceeds the maximum size of %s MB', $limit )
            : 'Remote file exceeds the maximum upload size';

        if( $length !== '' && ctype_digit( $length ) && (int) $length > $max ) {
            $body->close();
            throw new \Aimeos\Cms\Exception( $message );
        }

        $bytes = $body->read( min( 4096, $max + 1 ) );

        if( strlen( $bytes ) > $max ) {
            $body->close();
            throw new \Aimeos\Cms\Exception( $message );
        }

        $this->mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $bytes ) ?: 'application/octet-stream';

        // SVG (incl. gzip-compressed SVGZ) isn't supported by the image drivers but is stored as preview itself
        if( $driver && !in_array( $this->mime, ['image/svg+xml', 'application/gzip'] )
            && !$driver->supports( $this->mime ) )
        {
            $body->close();
            return null;
        }

        if( !( $tmp = tmpfile() ) ) {
            $body->close();
            throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
        }

        fwrite( $tmp, $bytes );
        $size = strlen( $bytes );

        while( !$body->eof() )
        {
            $chunk = $body->read( min( 1048576, $max - $size + 1 ) );
            $size += strlen( $chunk );

            if( $size > $max ) {
                $body->close();
                fclose( $tmp );
                throw new \Aimeos\Cms\Exception( $message );
            }

            fwrite( $tmp, $chunk );
        }

        $body->close();
        fseek( $tmp, 0 );

        return $tmp;
    }


    /**
     * Returns the new name for the uploaded file
     *
     * @param string $filename Name of the file
     * @param string|null $ext File extension to use, if not given, the original file extension is used
     * @param array<string, mixed> $size Image width and height, if used
     * @return string New file name
     */
    protected function filename( string $filename, ?string $ext = null, array $size = [] ) : string
    {
        $regex = '/([[:cntrl:]]|[[:blank:]]|\/|\.)+/smu';

        $ext = Utils::extension( $ext ?: pathinfo( $filename, PATHINFO_EXTENSION ) );
        $name = preg_replace( $regex, '', pathinfo( $filename, PATHINFO_FILENAME ) );

        $hash = strtr( base64_encode( random_bytes( 3 ) ), '+/', '-_' );

        return $name . '_' . ( $size['width'] ?? $size['height'] ?? '' ) . '_' . $hash . '.' . $ext;
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

        $this->mime = $this->mime ?: Utils::mimetype( $source, self::diskName( (string) $this->getAttribute( 'disk' ) ) );
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
        $this->mime = Utils::mimetype( (string) $this->path, self::diskName( (string) $this->getAttribute( 'disk' ) ) );
        $this->name = $this->name ?: pathinfo( $source->getClientOriginalName(), PATHINFO_BASENAME );

        if( $preview || str_starts_with( (string) $source->getMimeType(), 'image/' ) ) {
            $this->addPreviews( $preview ?? $source );
        }
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
     * Returns local storage paths used by file versions.
     *
     * @param iterable<Version> $versions
     * @return Collection<int, string>
     */
    protected static function versionPaths( iterable $versions ) : Collection
    {
        $paths = [];

        foreach( $versions as $version )
        {
            foreach( (array) $version->data->previews as $path ) {
                $paths[(string) $path] = true;
            }

            if( $version->data->path ) {
                $paths[(string) $version->data->path] = true;
            }
        }

        return collect( array_keys( $paths ) );
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
}
