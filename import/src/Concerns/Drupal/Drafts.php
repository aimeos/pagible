<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Drupal;

use Aimeos\Cms\Concerns\PendingVersions;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the newer draft revisions and the scheduled publication of the Drupal nodes as pending page versions.
 */
trait Drafts
{
    use PendingVersions;

    /** @var array<int, array<string, \stdClass>>|null Newest draft revisions by node ID and Drupal language code */
    protected ?array $drafts = null;


    /**
     * Creates the scheduled versions of the Scheduler module and the newer draft of the Content Moderation module.
     *
     * The publication and unpublication dates of the Scheduler module become versions which are
     * published by the "cms:publish" command at that time. A newer draft revision, e.g. of the
     * Content Moderation module, becomes the latest version of the page which isn't published.
     * Pending versions of previous imports are replaced if they changed.
     *
     * @param object{nid: int, type: string, title: string, status: int, langcode?: string, vid?: int, publish_on?: int|null, unpublish_on?: int|null} $node
     * @param array<string, mixed> $data Page data of the published version
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content of the published version
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     */
    protected function pending( PageView $page, object $node, array $data, array $content, bool $fallback ): void
    {
        $versions = [];
        $draft = $this->draft( $page, $node, $data, $fallback );
        $base = $draft ?? [$data, $content];

        if( ( $at = (int) ( $node->publish_on ?? 0 ) ) > time() ) {
            $versions[] = $base = [array_replace( $base[0], ['status' => Pages::STATUS_PUBLISHED] ), $base[1], $at];
        } elseif( $draft ) {
            $versions[] = [$draft[0], $draft[1], null];
            $base = [$data, $content];
        }

        if( ( $at = (int) ( $node->unpublish_on ?? 0 ) ) > time() ) {
            $versions[] = [array_replace( $base[0], ['status' => Pages::STATUS_DISABLED] ), $base[1], $at];
        }

        $this->saveVersions( $page, $versions );
    }


    /**
     * Returns the page data and content of a newer draft revision of the node.
     *
     * @param object{nid: int, type: string, title: string, langcode?: string, vid?: int} $node
     * @param array<string, mixed> $data Page data of the published version
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     * @return array{0: array<string, mixed>, 1: array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>, meta: array<string, mixed>}}|null Page data and content or NULL if there's no newer draft
     */
    protected function draft( PageView $page, object $node, array $data, bool $fallback ): ?array
    {
        $langcode = $node->langcode ?? '';

        if( !isset( $node->vid ) || !( $revision = $this->drafts()[$node->nid][$langcode] ?? null ) ) {
            return null;
        }

        $vid = (int) $revision->vid;
        $title = (string) $revision->title;
        // meta title like "[node:title] | [site:name]" of the draft revision
        $metaTitle = $this->revision( $node->nid, $vid, $title, fn() => $this->metaTitle( $node, $langcode ) );
        $draft = array_replace( $data, array_filter( [
            'name' => $data['name'] === $node->title ? $title : null,
            'title' => $metaTitle !== '' ? $metaTitle : $title,
        ] ) );
        $content = $this->nodeContent( $node, $langcode, $fallback, $vid, $title );

        // drafts which only differ in their revision aren't imported
        if( Pages::unchanged( $page, $draft, ['content' => $content['elements'], 'meta' => $content['meta']] ) ) {
            return null;
        }

        return [$draft, $content];
    }


    /**
     * Returns the newest revisions which are newer than the current revisions of the nodes.
     *
     * They are fetched at once for all nodes to avoid one query per node and language.
     *
     * @return array<int, array<string, \stdClass>> Revision rows with "vid" and "title" by node ID and Drupal language code
     */
    protected function drafts(): array
    {
        if( $this->drafts !== null ) {
            return $this->drafts;
        }

        $this->drafts = [];

        if( !$this->data->hasTable( 'node_field_revision' ) || !$this->data->hasColumn( 'node_field_data', 'vid' ) ) {
            return $this->drafts;
        }

        foreach( $this->reader->drafts( $this->nodes->keys()->map( fn( $id ) => (int) $id )->all() ) as $row ) {
            $this->drafts[(int) $row->nid][(string) $row->langcode] = $row;
        }

        // the field values of the drafts are fetched together in the import order of the nodes
        $this->data->drafts( array_map( fn( $rows ) => array_values( array_unique( array_map( fn( $row ) => (int) $row->vid, $rows ) ) ), $this->drafts ) );

        return $this->drafts;
    }
}
