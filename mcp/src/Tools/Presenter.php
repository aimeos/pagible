<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;


/**
 * Maps CMS models to the JSON structures returned by the tools.
 *
 * page(), element() and file() return the latest draft state from the eagerly
 * loaded "latest" version relation, item() the published projection of the
 * model. Only loaded attributes are accessed, so tools can select columns.
 */
class Presenter
{
    /**
     * Returns the draft state of a shared element.
     *
     * @param Element $item Element with its "latest" version relation loaded
     * @param bool $full TRUE to include the element data and publication state, FALSE for list entries
     * @return array<string, mixed> Element data
     */
    public static function element( Element $item, bool $full = true ) : array
    {
        $version = $item->latest;
        $data = (array) ( $version->data ?? [] );

        $result = [
            'id' => $item->id,
            'latest_id' => $item->latest_id,
            'type' => $data['type'] ?? $item->type ?? '',
            'name' => $data['name'] ?? '',
        ];

        if( $full )
        {
            $result += [
                'data' => $data['data'] ?? new \stdClass(),
                'published' => $version->published ?? false,
                'publish_at' => $version->publish_at ?? null,
            ];
        }

        return $result + self::meta( $item );
    }


    /**
     * Returns the draft state of a media file.
     *
     * @param File $item File with its "latest" version relation loaded
     * @param bool $full TRUE to include previews, descriptions and publication state, FALSE for list entries
     * @return array<string, mixed> File data
     */
    public static function file( File $item, bool $full = true ) : array
    {
        $version = $item->latest;
        $data = (array) ( $version->data ?? [] );
        $aux = (array) ( $version->aux ?? [] );

        $result = [
            'id' => $item->id,
            'latest_id' => $item->latest_id,
            'name' => $data['name'] ?? $item->name ?? '',
            'mime' => $data['mime'] ?? $item->mime ?? '',
            'path' => $data['path'] ?? $item->path ?? '',
        ];

        if( $full )
        {
            $result += [
                'disk' => $item->disk,
                'previews' => $data['previews'] ?? $item->previews ?? [],
                'description' => $aux['description'] ?? $item->description ?? new \stdClass(),
                'transcription' => $aux['transcription'] ?? $item->transcription ?? new \stdClass(),
                'published' => $version->published ?? false,
                'publish_at' => $version->publish_at ?? null,
            ];
        }

        return $result + self::meta( $item );
    }


    /**
     * Returns the published projection of a page, element or file.
     *
     * @param Base $item Page, element or file model
     * @param bool $link TRUE to add the parent ID and URL for pages
     * @return array<string, mixed> Visible model attributes
     */
    public static function item( Base $item, bool $link = false ) : array
    {
        if( $link && $item instanceof Page ) {
            return ['id' => $item->id, 'latest_id' => $item->latest_id, 'parent_id' => $item->parent_id]
                + $item->toArray() + ['url' => Utils::pageUrl( $item->path, $item->domain )];
        }

        return ['id' => $item->id, 'latest_id' => $item->latest_id] + $item->toArray();
    }


    /**
     * Returns the draft state of a page.
     *
     * @param Page $item Page with its "latest" version relation loaded
     * @param bool $full TRUE to include all version data, content, meta, config and publication state, FALSE for list entries
     * @return array<string, mixed> Page data
     */
    public static function page( Page $item, bool $full = true ) : array
    {
        $version = $item->latest;
        $data = (array) ( $version->data ?? [] );
        $aux = (array) ( $version->aux ?? [] );

        $result = [
            'id' => $item->id,
            'latest_id' => $item->latest_id,
            'parent_id' => $item->parent_id,
            'tag' => $data['tag'] ?? '',
            'path' => $data['path'] ?? '',
            'domain' => $data['domain'] ?? '',
            'to' => $data['to'] ?? '',
            'name' => $data['name'] ?? '',
            'title' => $data['title'] ?? '',
            'type' => $data['type'] ?? '',
            'theme' => $data['theme'] ?? '',
            'status' => $data['status'] ?? 0,
            'cache' => $data['cache'] ?? 0,
        ];

        if( $full )
        {
            $result = array_merge( $data, $result, [
                'content' => $aux['content'] ?? [],
                'meta' => $aux['meta'] ?? new \stdClass(),
                'config' => $aux['config'] ?? new \stdClass(),
                'published' => $version->published ?? false,
                'publish_at' => $version->publish_at ?? null,
            ] );
        }

        // language variant state, only if the columns are selected
        $attr = $item->getAttributes();

        foreach( ['source', 'stale'] as $name )
        {
            if( array_key_exists( $name, $attr ) ) {
                $result[$name] = $item->getAttribute( $name );
            }
        }

        return $result + self::meta( $item ) + ['url' => Utils::pageUrl( $data['path'] ?? '', $data['domain'] ?? null )];
    }


    /**
     * Returns the published and skipped items of a publish request.
     *
     * @param Collection<int, Base> $items Items returned by Publication::publish()
     * @param array<int, string> $ids Requested IDs
     * @param string|null $at Scheduled publish date or NULL for immediate publishing
     * @return array{published: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>} Publish result
     */
    public static function published( Collection $items, array $ids, ?string $at ) : array
    {
        $published = $skipped = [];

        foreach( $items as $item )
        {
            if( !$item->latest ) {
                $skipped[] = ['id' => $item->id, 'reason' => 'No draft version'];
            } elseif( $at && $item->latest->published ) {
                $skipped[] = ['id' => $item->id, 'reason' => 'Already published'];
            } elseif( $at ) {
                $published[] = ['id' => $item->id, 'name' => $item->getAttribute( 'name' ), 'scheduled_at' => $at];
            } else {
                $published[] = ['id' => $item->id, 'name' => $item->getAttribute( 'name' )] + ( $item instanceof Page ? ['path' => $item->path] : [] );
            }
        }

        foreach( array_diff( $ids, $items->pluck( 'id' )->all() ) as $id ) {
            $skipped[] = ['id' => $id, 'reason' => 'Not found'];
        }

        return ['published' => $published, 'skipped' => $skipped];
    }


    /**
     * Returns the selected columns of the related records as plain arrays.
     *
     * @param Relation<*, *, *> $relation Relation of the model, e.g. $file->bypages()
     * @param string ...$cols Columns to select, e.g. "cms_pages.id"
     * @return array<int, array<string, mixed>> List of records
     */
    public static function rows( Relation $relation, string ...$cols ) : array
    {
        return $relation->toBase()->select( $cols )->cursor()->map( fn( $row ) => (array) $row )->all();
    }


    /**
     * Returns the draft state after saving without the deletion and publication state.
     *
     * @param array<string, mixed> $data Result of page(), element() or file()
     * @param Base $item Saved page, element or file model
     * @return array<string, mixed> Draft data with the "changed" flag
     */
    public static function saved( array $data, Base $item ) : array
    {
        unset( $data['deleted'], $data['published'], $data['publish_at'] );
        return $data + ['changed' => $item->changed];
    }


    /**
     * Returns the version and timestamp fields shared by all draft structures.
     *
     * @param Element|File|Page $item Model with its "latest" version relation loaded
     * @return array<string, mixed> Language, editor, deletion state and timestamps
     */
    protected static function meta( Element|File|Page $item ) : array
    {
        $version = $item->latest;

        return [
            'lang' => $version->lang ?? '',
            'editor' => $version->editor ?? '',
            'deleted' => $item->trashed() || ( $item->getAttributes()['variant_deleted_at'] ?? null ) !== null,
            'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => ( $version->created_at ?? $item->updated_at )?->format( 'Y-m-d H:i:s' ),
        ];
    }
}
