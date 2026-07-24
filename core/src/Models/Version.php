<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\Validation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Collection;


/**
 * Version model
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $lang
 * @property \stdClass $data
 * @property \stdClass $aux
 * @property string|null $publish_at
 * @property bool $published
 * @property string $editor
 * @property string $versionable_id
 * @property string $versionable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> due(\DateTimeInterface $at)
 * @method static \Illuminate\Database\Eloquent\Builder<static> older(Version $version)
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Version extends Model
{
    use HasUuids;
    use Tenancy;

    /** @var list<class-string<Base>> Supported versionable models */
    public const TYPES = [Page::class, Element::class, File::class];

    /** @var list<string> Most frequently used version projection */
    public const SELECT_COLUMNS = [
        'id', 'tenant_id', 'versionable_id', 'versionable_type', 'data', 'lang', 'editor', 'published',
    ];

    /**
     * Boot the model.
     */
    protected static function booted() : void
    {
        static::creating( function( Version $version ) {
            if( $version->versionable_type === File::class )
            {
                $snapshot = File::snapshot( (array) $version->data );

                if( $snapshot['aux'] ) {
                    $version->data = (object) $snapshot['data'];
                    $version->aux = (object) array_replace( $snapshot['aux'], (array) $version->aux );
                }
            }
        } );

        static::saving( function( $version ) {
            $scheduled = $version->publish_at !== null ? 1 : 0;
            $data = $version->data ?? new \stdClass();

            if( ( $data->scheduled ?? null ) !== $scheduled ) {
                $data->scheduled = $scheduled;
                $version->data = $data;
            }
        } );
    }


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
        'lang' => null,
        'data' => '{}',
        'aux' => '{}',
        'publish_at' => null,
        'published' => false,
        'editor' => '',
    ];

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'object',
        'aux' => 'object',
    ];

    /**
     * The date format with milliseconds used by created_at.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'publish_at',
        'editor',
        'lang',
        'data',
        'aux',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_versions';


    /**
     * Returns the text content of the version.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        $data = $this->data ?? new \stdClass();
        $aux = $this->aux ?? new \stdClass();
        $parts = [
            $data->tag ?? '',
            $data->name ?? '',
            $data->title ?? '',
            $aux->meta->{'meta-tags'}->data->description ?? '',
        ];

        foreach( (array) ( $aux->description ?? [] ) as $lang => $value ) {
            $parts[] = $lang . ":\n" . $value;
        }

        foreach( (array) ( $aux->transcription ?? [] ) as $lang => $value ) {
            $parts[] = $lang . ":\n" . $value;
        }

        $config = \Aimeos\Cms\Schema::schemas( section: 'content' );
        $items = (array) ( $aux->content ?? [] );

        if( !empty( $items ) && $this->relationLoaded( 'elements' ) ) {
            foreach( $this->getRelation( 'elements' ) as $el ) {
                $items[] = $el;
            }
        }

        $items[] = $data;

        foreach( $items as $el )
        {
            $fields = (array) ( $config[$el->type ?? '']['fields'] ?? [] );

            if( empty( $fields ) ) {
                continue;
            }

            foreach( (array) ( $el->data ?? [] ) as $name => $value )
            {
                if( is_string( $value ) && isset( $fields[$name] )
                    && ( $fields[$name]['searchable'] ?? true )
                    && in_array( $fields[$name]['type'], ['markdown', 'plaintext', 'string', 'text'] )
                ) {
                    $parts[] = $value;
                }
            }
        }

        return trim( implode( "\n", $parts ) );
    }


    /**
     * Get the shared element attached to the version.
     *
     * @return BelongsToMany<Element, $this>
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_version_element' );
    }


    /**
     * Get all files referenced by the versioned data.
     *
     * @return BelongsToMany<File, $this>
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_version_file' );
    }


    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function freshTimestamp()
    {
        return Date::now();
    }


    /**
     * Get the connection name for the model.
     */
    public function getConnectionName()
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Returns the list of changed attributes.
     * Required to return the correct boolean value if the "published" property
     * is stored as integer in the database.
     *
     * @return array<string, mixed> List of changed attributes
     */
    public function getDirty()
    {
        $dirty = [];

        foreach( $this->getAttributes() as $key => $value )
        {
            if( $key === 'published' )
            {
                if( (bool)$value !== (bool)$this->original[$key] ) {
                    $dirty[$key] = $value;
                }

                continue;
            }

            if( !$this->originalIsEquivalent( $key ) ) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }


    /**
     * Maps the elements by ID automatically.
     *
     * @return Collection<string, Element> List elements with ID as keys and element models as values
     */
    public function getElementsAttribute() : Collection
    {
        $this->relationLoaded( 'elements' ) ?: $this->load( 'elements' );
        return $this->getRelation( 'elements' )->pluck( null, 'id' );
    }


    /**
     * Maps the files by ID automatically.
     *
     * @return Collection<string, File> List files with ID as keys and file models as values
     */
    public function getFilesAttribute() : Collection
    {
        $this->relationLoaded( 'files' ) ?: $this->load( 'files' );
        return $this->getRelation( 'files' )->pluck( null, 'id' );
    }


    /**
     * Limits the query to unpublished versions whose publication time is due.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeDue( Builder $query, \DateTimeInterface $at ) : Builder
    {
        return $query->where( 'publish_at', '<=', $at )->where( 'published', false );
    }


    /**
     * Limits the query to scheduled versions older than the given version.
     *
     * created_at provides portable creation order while ID breaks timestamp ties.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOlder( Builder $query, Version $version ) : Builder
    {
        return $query->where( fn( $query ) => $query
            ->where( 'publish_at', '<', $version->getRawOriginal( 'publish_at' ) )
            ->orWhere( fn( $query ) => $query->where( 'publish_at', $version->getRawOriginal( 'publish_at' ) )
                ->where( fn( $query ) => $query
                    ->where( 'created_at', '<', $version->getRawOriginal( 'created_at' ) )
                    ->orWhere( fn( $query ) => $query->where( 'created_at', $version->getRawOriginal( 'created_at' ) )
                        ->where( 'id', '<', $version->id ) ) ) ) );
    }


    /**
     * Disables using the updated_at column.
     * Versions are never updated, each one is created as a new entry.
     */
    public function getUpdatedAtColumn()
    {
        return null;
    }


    /**
     * Get the parent versionable model (page, file or element).
     *
     * @return MorphTo<Model, $this>
     */
    public function versionable() : MorphTo
    {
        return $this->morphTo();
    }


    /**
     * Interact with the "aux" property.
     *
     * Page versions keep meta/config in aux and file versions keep description/
     * transcription there. Canonicalizing page structures here prevents direct
     * version writers, restores and publishing from reintroducing legacy shapes.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "aux" property
     */
    protected function aux(): Attribute
    {
        return Attribute::make(
            set: function( $value ) {
                if( is_string( $value ) ) {
                    $value = json_decode( $value, true ) ?: [];
                }

                $aux = (array) ( $value ?? [] );
                if( array_key_exists( 'meta', $aux ) ) {
                    $aux['meta'] = Validation::structured( $aux['meta'], 'meta' );
                }

                if( array_key_exists( 'config', $aux ) ) {
                    $aux['config'] = Validation::structured( $aux['config'], 'config' );
                }

                return json_encode( $aux );
            },
        );
    }
}
