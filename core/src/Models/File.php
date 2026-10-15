<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Casts\AsString;
use Aimeos\Cms\Concerns\FilePreviews;
use Aimeos\Cms\Concerns\FileStorage;
use Aimeos\Cms\Concerns\Referenced;
use Aimeos\Cms\Jobs\DeleteFilePaths;
use Aimeos\Cms\ContentType;
use Aimeos\Cms\Files;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\LifecycleAction;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB;
use Aimeos\Cms\Tenancy;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;


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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageView> $bypages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Element> $byelements
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class File extends Base
{
    use FilePreviews;
    use FileStorage;
    use Referenced;

    public const PERM = ContentType::File->value;
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
        'name' => AsString::class,
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
     * Returns the value of the latest version or the stored value if the latest version has none.
     *
     * @param string $name Name of the file property, e.g. "path" or "previews"
     * @return mixed Value of the latest version or stored value
     */
    public function latestValue( string $name ) : mixed
    {
        return $this->latest?->data->{$name} ?? $this->getAttribute( $name );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder instance for pruning
     */
    public function prunable() : Builder
    {
        return static::expired()
            ->select( 'id', 'tenant_id', 'path', 'previews', 'deleted_at' )
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
        return Files::storageLock( (string) $this->tenant_id, function() {
            // the file may have been referenced again since it has been selected
            if( !$this->prunable()->withTrashed()->whereKey( $this->id )->exists() ) {
                return false;
            }

            $this->pruning();
            return $this->forceDelete();
        } );
    }


    /**
     * Applies a lifecycle action to the locked files and removes the stored files when purging.
     *
     * @param EloquentCollection<int, Base> $items Files
     * @param LifecycleAction $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    public static function lifecycle( EloquentCollection $items, LifecycleAction $action, string $editor ) : void
    {
        if( $action === LifecycleAction::Purged ) {
            self::purgeMany( Tenancy::value(), $items );
        }

        parent::lifecycle( $items, $action, $editor );
    }


    /**
     * Invalidates the cached pages which reference the files.
     *
     * @param array<int, string> $ids File IDs
     */
    public static function invalidateRefs( array $ids ) : void
    {
        Invalidation::refs( [], $ids );
    }


    /**
     * Returns the columns a lifecycle action needs in addition to the identifying ones.
     *
     * @param LifecycleAction $action Lifecycle action
     * @return array<int, string> Column names
     */
    public static function lifecycleColumns( LifecycleAction $action ) : array
    {
        return match( $action ) {
            LifecycleAction::Purged => ['path', 'previews'],
            LifecycleAction::Restored => self::SELECT_COLUMNS,
            default => [],
        };
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
        return Files::storageLock( Tenancy::value(), $fcn );
    }


    /**
     * Tests if purging removes stored data which can fail per item, so failed batches are purged item by item.
     */
    public static function purgesStorage() : bool
    {
        return true;
    }


    /**
     * Deletes all versions and queues storage cleanup for a locked file batch.
     *
     * @param Collection<int, covariant Base> $files
     */
    protected static function purgeMany( string $tenant, Collection $files ) : void
    {
        /** @var array<string> $ids */
        $ids = $files->pluck( 'id' )->all();

        do
        {
            $versions = Version::withoutTenancy()->select( 'id', 'data' )
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'versionable_id', $ids )
                ->where( 'versionable_type', File::class )
                ->orderBy( 'id' )->limit( DB::IN_SIZE )->get();

            if( !$versions->isEmpty() ) {
                Version::withoutTenancy()->where( 'tenant_id', $tenant )
                    ->whereIn( 'id', $versions->modelKeys() )->delete();
                self::deletePaths( self::paths( $versions->pluck( 'data' ) ), $tenant );
            }
        }
        while( $versions->count() === DB::IN_SIZE );

        self::deletePaths( self::paths( $files ), $tenant );
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
        return $this->searchableVersion( ['name', 'mime', 'description', 'transcription', 'deleted_at', 'latest_id'], 'mime' );
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
            DB::connection()->afterCommit( fn() => Files::deferStorage( $tenant,
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
}
