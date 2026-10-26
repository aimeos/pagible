<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\ContentType;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\BulkResult;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Saves the same input to several items of one type.
 *
 * @internal
 */
final class Bulk
{
    /**
     * Saves the same input to several items of one type as a single best-effort batch.
     *
     * @param class-string<Element>|class-string<File>|class-string<PageView> $model Model class being saved
     * @param array<string> $ids Item ids to save, in processing order
     * @param array<string, mixed> $input Shared fields applied to every item
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param \Closure(string, array<string, array<string, array<string>>>, string): (PageView|File|Element|null) $save Loads one locked row and applies the change using the prefetched references
     * @param string|null $lang Language of the saved page variants or NULL for the source variants
     * @return BulkResult
     */
    public static function save( string $model, array $ids, array $input, ?Authenticatable $user, \Closure $save,
        ?string $lang = null ) : BulkResult
    {
        if( empty( $ids ) || empty( $input ) ) {
            return new BulkResult();
        }

        $editor = Utils::editor( $user );
        $ids = $model::bulkIds( $ids );

        $keys = $langs = [];

        // suppress Scout's per-save reindex; the whole batch is reindexed once below
        $latest = Scout::mute( [$model], function() use ( $ids, $lang, $model, $save, $editor, &$keys, &$langs ) {
            $result = [];

            foreach( array_chunk( $ids, Base::CHUNK ) as $chunk )
            {
                // references of the latest versions which are copied to the new ones
                $prefetched = $model::refs( $chunk, $lang );

                foreach( $chunk as $id )
                {
                    if( !( $item = self::item( $model, $id, $prefetched, $editor, $save ) ) ) {
                        continue;
                    }

                    $result[(string) $item->id] = (string) $item->latest_id;
                    $keys[] = (string) ( $item->getVersionKey() ?? $item->id );

                    if( ( $itemLang = $item->eventLang() ) !== null ) {
                        $langs[(string) $item->id] = $itemLang;
                    }
                }
            }

            return $result;
        } );

        $saved = array_keys( $latest );

        // reindex the saved items once, chunked like cms:index; drop the soft-delete scope so
        // trashed items (recursive saves include them) are reindexed regardless of scout.soft_delete,
        // only the saved variants of pages change
        if( $saved ) {
            Scout::index( $model, $keys, keys: true );
        }

        $result = new BulkResult(
            ids: $saved,
            latest: $latest,
            data: $input + ( $lang !== null ? ['lang' => $lang] : [] ) + ['published' => false, 'updated_at' => (string) now()],
            failed: count( $ids ) - count( $saved ),
        );

        Base::announceBulk( ContentType::from( $model::PERM ), $result->ids, $result->latest, $result->data, $editor, langs: $langs );

        // versions belong to the version key, e.g. the page variant
        $model::locked( fn() => PruneVersions::dispatchChunked( $model, $keys ) );

        return $result;
    }


    /**
     * Saves one item of a bulk operation in its own transaction.
     *
     * Failures are reported and counted by the caller instead of aborting the whole batch.
     * Denied items are expected in mixed-language batches and aren't reported.
     *
     * @param class-string<Element>|class-string<File>|class-string<PageView> $model Model class being saved
     * @param string $id Item ID
     * @param array<string, array<string, array<string>>> $prefetched References of the latest versions
     * @param string $editor Name of the editor
     * @param \Closure(string, array<string, array<string, array<string>>>, string): (PageView|File|Element|null) $save Saves the item
     * @return PageView|File|Element|null Saved item or NULL if it was skipped or failed
     */
    private static function item( string $model, string $id, array $prefetched, string $editor, \Closure $save ) : PageView|File|Element|null
    {
        try {
            return $model::locked( fn() => DB::transaction( fn() => $save( $id, $prefetched, $editor ) ) );
        } catch( PermissionException ) {
            return null;
        } catch( \Exception $e ) {
            report( $e );
        }

        return null;
    }
}
