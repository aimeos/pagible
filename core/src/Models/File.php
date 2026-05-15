<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasChanged;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Laravel\Scout\Searchable;


/**
 * File model
 *
 * @property string $id
 * @property string $tenant_id
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
    use HasChanged;
    use HasUuids;
    use SoftDeletes;
    use Searchable;
    use Prunable;
    use Tenancy;


    /** @var list<string> Columns for eager-loading file relations */
    public const SELECT_COLS = ['cms_files.id', 'name', 'mime', 'path', 'previews', 'description', 'transcription'];


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
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
        'name' => 'string',
        'previews' => 'object',
        'description' => 'object',
        'transcription' => 'object',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
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
            throw new \RuntimeException( 'Invalid file upload' );
        }

        $disk = Storage::disk( config( 'cms.disk', 'public' ) );
        $dir = rtrim( 'cms/' . \Aimeos\Cms\Tenancy::value(), '/' );

        $name = $this->filename( $upload->getClientOriginalName() );
        $path = $dir . '/' . $name;

        if( $upload->getMimeType() === 'image/svg+xml' )
        {
            $content = file_get_contents( $upload->getRealPath() );

            if( !( $content = \Aimeos\Cms\Utils::cleanSvg( $content ) ) ) {
                $msg = 'Invalid file "%s"';
                throw new \RuntimeException( sprintf( $msg, $upload->getClientOriginalName() ) );
            }

            if( !$disk->put( $path, $content ) ) {
                $msg = 'Unable to store file "%s" to "%s"';
                throw new \RuntimeException( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
            }
        }
        else
        {
            if( !$disk->putFileAs( $dir, $upload, $name ) ) {
                $msg = 'Unable to store file "%s" to "%s"';
                throw new \RuntimeException( sprintf( $msg, $upload->getClientOriginalName(), $path ) );
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
        $disk = Storage::disk( config( 'cms.disk', 'public' ) );
        $dir = rtrim( 'cms/' . \Aimeos\Cms\Tenancy::value(), '/' );

        /** @var ImageManager $manager */
        $manager = ImageManager::withDriver( '\\Intervention\\Image\\Drivers\\' . ucFirst( config( 'cms.image.driver', 'gd' ) ) . '\Driver' );
        $ext = $manager->driver()->supports( 'image/webp' ) ? 'webp' : 'jpg';

        if( is_string( $resource ) && \Aimeos\Cms\Utils::isValidUrl( $resource ) ) {
            $tmp = tmpfile();
            $response = Http::withOptions( ['sink' => $tmp] )->get( $resource );

            if( !$response->successful() ) {
                fclose( $tmp );
                throw new \RuntimeException( sprintf( 'Failed to download "%s"', $resource ) );
            }

            fseek( $tmp, 0 );
            $resource = $tmp;
        }

        if( $resource instanceof UploadedFile ) {
            $filename = $resource->getClientOriginalName();
            $mime = $resource->getClientMimeType();
        } else {
            $filename = $this->name;
            $mime = $this->mime;
        }

        if( !$manager->driver()->supports( $mime ) ) {
            return $this;
        }

        $file = $manager->read( $resource );

        $this->previews = [];
        $map = [];

        foreach( $sizes as $size )
        {
            $image = ( clone $file )->scaleDown( $size['width'] ?? null, $size['height'] ?? null );
            $ptr = $image->encodeByExtension( $ext, quality: 90 )->toFilePointer();
            $path = $dir . '/' . $this->filename( $filename, $ext, $size );

            if( $disk->put( $path, $ptr, 'public' ) ) {
                $map[$image->width()] = $path;
            }

            unset( $image, $ptr );
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
     * Enforce JSON columns to return object.
     *
     * @param string $key Attribute name
     * @return mixed Attribute value
     */
    public function getAttribute( $key )
    {
        $value = parent::getAttribute( $key );
        return is_null( $value ) && in_array( $key, ['description', 'previews', 'transcription'] ) ? new \stdClass() : $value;
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
     * Publish the given version of the element.
     *
     * @param Version $version The version to publish
     * @return self The current instance for method chaining
     */
    public function publish( Version $version ) : self
    {
        $path = $this->path;
        $previews = $this->previews;

        $this->forceFill( array_intersect_key( (array) $version->data, array_flip( $this->getFillable() ) ) );
        $this->previews = (array) $version->data?->previews;
        $this->path = $version->data?->path;
        $this->mime = $version->data?->mime;
        $this->editor = $version->editor;
        $this->setRelation( 'latest', $version );
        $this->save();

        if( !$version->published ) {
            $version->published = true;
            $version->save();
        }

        return $this;
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
     * Removes the file from the storage
     *
     * @return self The current instance for method chaining
     */
    public function removeFile() : self
    {
        if( $this->path && str_starts_with( $this->path, 'http' ) ) {
            Storage::disk( config( 'cms.disk', 'public' ) )->delete( $this->path );
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
        $disk = Storage::disk( config( 'cms.disk', 'public' ) );

        $previews = array_values( (array) $this->previews );

        if( !empty( $previews ) ) {
            $disk->delete( $previews );
        }

        $this->previews = [];
        return $this;
    }


    /**
     * Removes all versions of the file except the latest versions and deletes the stored files
     * of the older versions.
     *
     * @return static The current instance for method chaining
     */
    public function removeVersions() : static
    {
        $num = config( 'cms.versions', 10 );

        $drop = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->orderByDesc( 'created_at' )
            ->offset( $num )
            ->limit( 10 )
            ->get( ['id', 'data'] );

        if( $drop->isEmpty() ) {
            return $this;
        }

        $keep = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->orderByDesc( 'created_at' )
            ->limit( $num )
            ->pluck( 'data->path' )
            ->push( $this->path )
            ->filter();

        $rmPaths = $drop->flatMap( function( $v ) {
            $paths = array_values( (array) ( $v->data->previews ?? [] ) );

            if( $v->data?->path && !str_starts_with( (string) $v->data->path, 'http' ) ) {
                $paths[] = $v->data->path;
            }

            return $paths;
        } )
        ->filter()
        ->unique()
        ->diff( $keep );

        if( !$rmPaths->isEmpty() ) {
            Storage::disk( config( 'cms.disk', 'public' ) )->delete( $rmPaths );
        }

        Version::whereIn( 'id', $drop->pluck( 'id' ) )->forceDelete();

        return $this;
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
        return $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published' )] );
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

        $ext = $ext ?: preg_replace( $regex, '-', pathinfo( $filename, PATHINFO_EXTENSION ) );
        $name = preg_replace( $regex, '', pathinfo( $filename, PATHINFO_FILENAME ) );

        $hash = substr( md5( microtime(true) . getmypid() . rand(0, 1000) ), -4 );

        return $name . '_' . ( $size['width'] ?? $size['height'] ?? '' ) . '_' . $hash . '.' . $ext;
    }


    /**
     * Prepare the model for pruning.
     */
    protected function pruning() : void
    {
        $store = Storage::disk( config( 'cms.disk', 'public' ) );

        Version::select( 'id', 'data' )->where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->chunk( 50, function( $versions ) use ( $store ) {

                $paths = $versions->flatMap( function( $v ) {
                    $paths = array_values( (array) ( $v->data->previews ?? [] ) );

                    if( $v->data?->path && !str_starts_with( (string) $v->data->path, 'http' ) ) {
                        $paths[] = $v->data->path;
                    }

                    return $paths;
                } )
                ->filter()
                ->unique();

                if( !$paths->isEmpty() ) {
                    $store->delete( $paths );
                }
            } );

        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->delete();

        $paths = array_values( (array) $this->previews );

        if( !str_starts_with( (string) $this->path, 'http' ) ) {
            $paths[] = $this->path;
        }

        if( !empty( $paths ) ) {
            $store->delete( $paths );
        }
    }


    /**
     * Interact with the tag property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "tag" property
     */
    protected function tag(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
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
}
