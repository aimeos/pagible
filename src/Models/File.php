<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;


/**
 * File model
 */
class File extends Model
{
    use HasUuids;
    use SoftDeletes;
    use Prunable;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array
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
     * @var array
     */
    protected $casts = [
        'name' => 'string',
        'previews' => 'object',
        'description' => 'object',
        'transcription' => 'object',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
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
     * @var array
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

        if( !$disk->putFileAs( $dir, $upload, $name ) ) {
            throw new \RuntimeException( sprintf( 'Unable to store file "%s" to "%s"', $upload->getClientOriginalName(), $dir . '/' . $name ) );
        }

        $this->path = $dir . '/' . $name;
        return $this;
    }


    /**
     * Creates and adds the preview images
     *
     * @param UploadedFile|string $resource File upload or URL to the file
     * @return self The current instance for method chaining
     */
    public function addPreviews( $resource ) : self
    {
        $sizes = config( 'cms.image.preview-sizes', [[]] );
        $disk = Storage::disk( config( 'cms.disk', 'public' ) );
        $dir = rtrim( 'cms/' . \Aimeos\Cms\Tenancy::value(), '/' );

        $driver = ucFirst( config( 'cms.image.driver', 'gd' ) );
        $manager = ImageManager::withDriver( '\\Intervention\\Image\\Drivers\\' . $driver . '\Driver' );
        $ext = $manager->driver()->supports( 'image/webp' ) ? 'webp' : 'jpg';

        if( is_string( $resource ) && str_starts_with( $resource, 'http' ) ) {
            $resource = Http::withOptions( ['stream' => true] )->get( $resource )->getBody()->detach();
        }

        $filename = $resource instanceof UploadedFile ? $resource->getClientOriginalName() : (string) $this->name;

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

            $this->previews = $map;
        }

        return $this;
    }


    /**
     * Get all (shared) content elements referencing the file.
     *
     * @return BelongsToMany Eloquent relationship to the element referencing the file
     */
    public function byelements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_element_file' )
            ->select('id', 'type', 'name' );
    }


    /**
     * Get all pages referencing the file.
     *
     * @return BelongsToMany Eloquent relationship to the pages referencing the file
     */
    public function bypages() : BelongsToMany
    {
        return $this->belongsToMany( Page::class, 'cms_page_file' )
            ->select('id', 'path', 'name' );
    }


    /**
     * Get all versions referencing the file.
     *
     * @return BelongsToMany Eloquent relationship to the versions referencing the file
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
     * Get the connection name for the model.
     *
     * @return string The name of the database connection to use for the model
     */
    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Get the page's latest head/meta data.
     *
     * @return MorphOne Eloquent relationship to the latest version of the file
     */
    public function latest() : MorphOne
    {
        return $this->morphOne( Version::class, 'versionable' )->latestOfMany();
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

        DB::connection( $this->getConnectionName() )->transaction( function() use ( $version ) {

            $this->fill( (array) $version->data );
            $this->previews = (array) $version->data->previews ?? [];
            $this->path = $version->data->path;
            $this->mime = $version->data->mime;
            $this->editor = $version->editor;
            $this->save();

            $version->published = true;
            $version->save();

        }, 3 );

        $num = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->where( 'data->path', $path )
            ->count();

        if( $num === 0 )
        {
            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $disk->delete( $path );

            foreach( $previews as $filepath ) {
                $disk->delete( $filepath );
            }
        }

        return $this;
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder Eloquent query builder instance for pruning
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) )
            ->doesntHave( 'versions' )->doesntHave( 'pages' )->doesntHave( 'elements' );
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

        foreach( $this->previews as $path )
        {
            $disk->delete( $path );
            unset( $this->previews[$path] );
        }

        return $this;
    }


    /**
     * Removes all versions of the file except the latest versions and deletes the stored files
     * of the older versions.
     *
     * @return self The current instance for method chaining
     */
    public function removeVersions() : self
    {
        $num = config( 'cms.versions', 10 );

        $versions = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->orderBy( 'id', 'desc' )
            ->take( $num + 10 ) // keep $num versions, delete up to 10 older versions
            ->get();

        if( $versions->count() <= $num ) {
            return $this;
        }

        $paths = [$this->path => true];

        foreach( $versions->slice( $num ) as $version )
        {
            if( $version->data->path ) {
                $paths[$version->data->path] = true;
            }
        }

        $toDelete = $versions->skip( $num );
        $disk = Storage::disk( config( 'cms.storage.disk', 'public' ) );

        foreach( $toDelete as $version )
        {
            if( isset( $paths[$version->data->path] ) ) {
                continue;
            }

            $disk->delete( $version->data->path );

            foreach( $version->data['previews'] as $path ) {
                $disk->delete( $path );
            }
        }

        Version::whereIn( 'versionable_id', $toDelete->pluck( 'id' ) )
            ->where( 'versionable_type', File::class )
            ->forceDelete();

        return $this;
    }


    /**
     * Get all of the files's versions.
     *
     * @return MorphMany Eloquent relationship to the versions of the file
     */
    public function versions() : MorphMany
    {
        return $this->morphMany( Version::class, 'versionable' );
    }


    /**
     * Interact with the "name" property.
     *
     * @return Attribute Eloquent attribute for the "name" property
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
     * @return Attribute Eloquent attribute for the "description" property
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
     * @param array $size Image width and height, if used
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

        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->chunk( 100, function( $versions ) use ( $store ) {
                foreach( $versions as $version )
                {
                    foreach( $version->data->previews ?? [] as $path ) {
                        $store->delete( $path );
                    }

                    if( $version->data->path ?? null ) {
                        $store->delete( $version->data->path );
                    }
                }
            } );

        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', File::class )
            ->delete();

        foreach( $this->previews as $path ) {
            $store->delete( $path );
        }

        if( $this->path ) {
            $store->delete( $this->path );
        }
    }


    /**
     * Interact with the tag property.
     *
     * @return Attribute Eloquent attribute for the "tag" property
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
     * @return Attribute Eloquent attribute for the "transcription" property
     */
    protected function transcription(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ?? new \stdClass() )
        );
    }
}
