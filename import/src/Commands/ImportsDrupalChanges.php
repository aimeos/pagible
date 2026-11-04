<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Keeps the pages of the Drupal nodes which weren't changed since the date of the --since option.
 */
trait ImportsDrupalChanges
{
    /**
     * Returns the last modification timestamps of the nodes including their translations and draft revisions.
     *
     * Nodes whose menu links, terms or media were changed since the date are returned as changed too.
     *
     * @param array<int, int> $nids Node IDs
     * @param int $since Timestamp of the --since option
     * @return array<int, int> Timestamps by node ID
     */
    protected function fetchChanged( array $nids, int $since ): array
    {
        if( !$this->data->hasColumn( 'node_field_data', 'changed' ) ) {
            $this->warn( 'No modification dates of the Drupal nodes found, all nodes are imported.' );
            return [];
        }

        $changed = $this->reader->changed( $nids );

        foreach( $this->tracker->nodes( $since, $nids, $this->referenceFields() ) as $nid ) {
            $changed[$nid] = max( $changed[$nid] ?? 0, $since );
        }

        return $changed;
    }


    /**
     * Returns the fields which reference custom blocks or media or may embed them.
     *
     * @return array<string, array{0: string, 1: string, 2: string}> Entity type, field name and "block_content", "media" or "text" by field table
     */
    protected function referenceFields(): array
    {
        $fields = [];

        foreach( $this->data->fieldStorages() as $key => $storage )
        {
            [$type, $field] = explode( '.', $key, 2 );
            $target = (string) ( $storage['settings']['target_type'] ?? '' );

            if( $storage['type'] === 'entity_reference' && in_array( $target, ['block_content', 'media'], true ) ) {
                $fields[$this->data->fieldTable( $type, $field )] = [$type, $field, $target];
            } elseif( in_array( $storage['type'], ['text', 'text_long', 'text_with_summary'], true ) ) {
                $fields[$this->data->fieldTable( $type, $field )] = [$type, $field, 'text'];
            }
        }

        return $fields;
    }


    /**
     * Updates the shared elements of the custom blocks changed since the date of the --since option.
     *
     * The blocks are only updated when the pages showing them are rendered otherwise, which doesn't
     * happen for the pages of unchanged nodes. If the number of shared elements of a block changed,
     * the pages showing it are rendered again to reference them.
     */
    protected function refreshBlocks(): void
    {
        if( $this->since === null || !( $uuids = $this->tracker->blocks( $this->since ) ) ) {
            return;
        }

        $langcodes = $this->nodes->pluck( 'langcode' )->map( strval( ... ) )->merge( $this->langcodes )->unique()->all();

        foreach( $this->blocks->refresh( $uuids, $langcodes, $this->nodes, $this->transaction( ... ) ) as $nid ) {
            $this->changed[$nid] = $this->since;
        }
    }


    /**
     * Keeps the page of a node which wasn't changed since the date of the --since option.
     *
     * The page is only moved below the new parent page and its access is updated. Its translations
     * imported before are kept too.
     *
     * @param object{nid: int, title: string, status: int, created?: int, changed?: int, langcode?: string} $node
     * @param array<int, string>|false|null $access Access values from nodeAccess() or FALSE to keep the access
     */
    protected function keep( object $node, PageView $page, PageView $parent, string $slug, array|false|null $access ): PageView
    {
        $this->moveBelow( $page, $parent );

        $this->touch( $page, $node );
        $this->setAccess( $page, $access );

        $langcodes = array_keys( $this->translated[$node->nid] ?? [] );
        // the variants of all languages are fetched at once instead of one query per translation
        $variants = count( $langcodes ) ? $this->variants( $page ) : [];

        foreach( $langcodes as $langcode )
        {
            $lang = $this->languages->language( (string) $langcode )['lang'] ?? $this->lang;
            $variant = $lang !== $this->lang ? $variants[$lang] ?? null : null;

            if( $variant && $variant->editor === $this->editor ) {
                $this->markSaved( $variant->variant_id );
            }
        }

        return $this->imported( $node->nid, $page, $node->title, $slug, Status::Unchanged );
    }
}
