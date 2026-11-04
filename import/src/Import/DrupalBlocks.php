<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\Element;
use Illuminate\Support\Collection;


/**
 * Imports the custom blocks of the Drupal theme regions and the ones referenced by fields as shared elements.
 */
class DrupalBlocks
{
    /** @var array<int, string> IDs of the shared elements of the custom blocks referenced by the fields rendered by collect() */
    protected array $referenced = [];
    /** @var array<string, array{elements: array<int, array<string, mixed>>, elementIds: array<int, string>}> References to the shared elements by block and language */
    protected array $shared = [];
    /** @var array<int, array<string, string>>|null Lower case descriptions of all custom blocks by ID and language code, "" for the default language */
    protected ?array $blockInfos = null;
    /** @var array<string, array<string, int>> Number of custom blocks by language code and lower case description */
    protected array $blockCounts = [];
    /** @var array<string, bool> Keys and language codes of the refreshed custom blocks whose number of shared elements changed */
    protected array $resized = [];
    /** @var array<string, bool> IDs of the shared elements created or updated by this import */
    protected array $saved = [];
    /** TRUE while the content of a shared element is rendered, which can't reference other shared elements */
    protected bool $inline = false;


    /**
     * Initializes the object.
     *
     * @param DrupalReader $reader Reader for the Drupal database
     * @param DrupalData $data Configuration and entities of the Drupal database
     * @param DrupalContent $content Renders the fields of the custom blocks
     * @param DrupalLanguages $languages Languages of the imported pages
     * @param DrupalPlacements $placements Placements and visibility of the custom blocks in the theme regions
     * @param \Closure(string, string, array<string, mixed>, array<int, string>): string $save Saves the shared element by name, language, element and file IDs and returns its ID
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalReader $reader,
        protected DrupalData $data,
        protected DrupalContent $content,
        protected DrupalLanguages $languages,
        protected DrupalPlacements $placements,
        protected \Closure $save,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Executes the function and returns its result with the IDs of the shared elements of the custom blocks referenced by the rendered fields.
     *
     * @template T
     * @param \Closure(): T $fn Renders the content
     * @return array{0: T, 1: array<int, string>} Result of the function and the IDs of the shared elements
     */
    public function collect( \Closure $fn ): array
    {
        $referenced = $this->referenced;
        $this->referenced = [];

        try {
            return [$fn(), $this->referenced];
        } finally {
            $this->referenced = $referenced;
        }
    }


    /**
     * Returns the references to the shared elements of the custom blocks shown on the page of the node.
     *
     * @param object{nid: int, type: string} $node
     * @param string $langcode Drupal language code of the page
     * @return array{before: array<int, array<string, mixed>>, elements: array<int, array<string, mixed>>, elementIds: array<int, string>} References shown before and after the node content and the IDs of the shared elements
     */
    public function placed( object $node, string $langcode ): array
    {
        $before = $elements = $elementIds = [];

        foreach( $this->placements->all() as $block )
        {
            if( !$this->placements->visible( $block, $node, $langcode ) ) {
                continue;
            }

            $key = $block['key'] . ':' . $langcode;
            $shared = $this->shared[$key] ??= $this->share( $block, $langcode );

            $block['before'] ? array_push( $before, ...$shared['elements'] ) : array_push( $elements, ...$shared['elements'] );
            array_push( $elementIds, ...$shared['elementIds'] );
        }

        return ['before' => $before, 'elements' => $elements, 'elementIds' => array_values( array_unique( $elementIds ) )];
    }


    /**
     * Updates the shared elements of the changed custom blocks which were imported before.
     *
     * Only shared elements of previous imports are updated, no new ones are created for languages
     * without pages showing the block.
     *
     * @param array<int, string> $uuids UUIDs of the changed custom blocks
     * @param array<int, string> $langcodes Drupal language codes of the imported pages
     * @param Collection<int|string, mixed> $nodes Imported nodes
     * @param \Closure(string, \Closure(): array{elements: array<int, array<string, mixed>>, elementIds: array<int, string>}): (array{elements: array<int, array<string, mixed>>, elementIds: array<int, string>}|null) $transaction Executes the function by label in a transaction
     * @return array<int, int> IDs of the nodes which must be rendered again because the number of shared elements of their blocks changed
     */
    public function refresh( array $uuids, array $langcodes, Collection $nodes, \Closure $transaction ): array
    {
        $nids = [];

        foreach( $this->placements->all() as $block )
        {
            if( !in_array( $block['uuid'], $uuids, true ) ) {
                continue;
            }

            foreach( $langcodes as $langcode )
            {
                $key = $block['key'] . ':' . $langcode;

                if( !$this->languages->language( $langcode ) || isset( $this->shared[$key] ) ) {
                    continue;
                }

                // blocks without shared elements in the language are rendered with the pages if needed
                if( $refs = $transaction( "custom block {$block['key']} [{$langcode}]", fn() => $this->share( $block, $langcode, true ) ) ) {
                    $refs['elementIds'] ? $this->shared[$key] = $refs : null;
                }

                if( isset( $this->resized[$key] ) )
                {
                    foreach( $nodes as $node )
                    {
                        if( $this->placements->visible( $block, $node, $langcode ) ) {
                            $nids[] = (int) $node->nid;
                        }
                    }
                }
            }
        }

        return $nids;
    }


    /**
     * Returns the content of a published custom block referenced by a field.
     *
     * The block becomes shared elements like the blocks placed in the theme regions, so all pages
     * referencing it show the same content. Blocks referenced within shared elements are added
     * to their content because shared elements can't reference other shared elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    public function render( int $id, string $langcode, bool $fallback, int $depth ): ?array
    {
        $block = $this->data->entity( 'block_content', 'id', $id );
        $data = $this->data->entity( 'block_content_field_data', 'id', $id, $langcode );

        if( !$block || ( isset( $data->status ) && !$data->status ) ) {
            return null;
        }

        if( $this->inline || !$this->languages->language( $langcode ) ) {
            return $this->content->renderFields( 'block_content', (string) $block->type, $id, $langcode, $fallback, depth: $depth );
        }

        $refs = $this->shared["block_content.{$id}:{$langcode}"]
            ??= $this->share( ['key' => "block_content.{$id}", 'uuid' => (string) $block->uuid, 'group' => 'main'], $langcode );
        array_push( $this->referenced, ...$refs['elementIds'] );

        return ['elements' => $refs['elements'], 'fileIds' => []];
    }


    /**
     * Returns the IDs of the shared elements created or updated by this import.
     *
     * @return array<int, string>
     */
    public function saved(): array
    {
        return array_map( strval( ... ), array_keys( $this->saved ) );
    }


    /**
     * Creates or updates the shared elements of the custom block in the language.
     *
     * Each content element of the block becomes a shared element named after the block description,
     * e.g. "Contact" or "Contact 2". Blocks whose description is used by other blocks too get their
     * ID added, e.g. "Contact (5)". Shared elements changed in Pagible since aren't overwritten and
     * unchanged ones don't get a new version.
     *
     * @param array{key: string, uuid: string, group: string} $block Block placement
     * @param string $langcode Drupal language code
     * @param bool $existing TRUE to update the shared elements of previous imports only
     * @return array{elements: array<int, array<string, mixed>>, elementIds: array<int, string>} References to the shared elements and their IDs
     */
    protected function share( array $block, string $langcode, bool $existing = false ): array
    {
        $refs = ['elements' => [], 'elementIds' => []];
        $entity = $this->data->entity( 'block_content', 'uuid', $block['uuid'] );
        $language = $this->languages->language( $langcode );

        if( !$entity || !$language )
        {
            ( $this->warnOnce )( "block:{$block['uuid']}", "  Skipped custom block {$block['key']} (not found)" );
            return $refs;
        }

        $data = $this->data->entity( 'block_content_field_data', 'id', (int) $entity->id, $langcode );

        if( isset( $data->status ) && !$data->status ) {
            return $refs;
        }

        $name = trim( (string) ( $data->info ?? '' ) ) ?: $block['key'];
        // descriptions of non-reusable blocks aren't counted, so they always get their ID added
        $name = $this->count( $name, $langcode ) > 1 || ( isset( $data->reusable ) && !$data->reusable ) ? "{$name} ({$entity->id})" : $name;

        if( $existing && !Element::where( 'lang', $language['lang'] )->whereIn( 'name', [$name, $name . ' 1'] )->exists() ) {
            return $refs;
        }

        $inline = $this->inline;
        $this->inline = true;

        try {
            $content = $this->content->renderFields( 'block_content', (string) $entity->type, (int) $entity->id, $langcode, fallback: true, depth: 1 );
        } finally {
            $this->inline = $inline;
        }

        $this->content->removed( "custom block {$name} [{$langcode}]" );

        // pages showing the block must reference other shared elements if their number changed
        if( $existing )
        {
            $count = count( $content['elements'] );
            $expected = $count > 1 ? array_map( fn( $idx ) => "{$name} {$idx}", range( 1, $count ) ) : ( $count ? [$name] : [] );
            $found = Element::where( 'lang', $language['lang'] )
                ->whereIn( 'name', [$name, ...array_map( fn( $idx ) => "{$name} {$idx}", range( 1, $count + 1 ) )] )->pluck( 'name' )->all();

            if( array_diff( $found, $expected ) || array_diff( $expected, $found ) ) {
                $this->resized[$block['key'] . ':' . $langcode] = true;
            }
        }
        $count = count( $content['elements'] );

        foreach( $content['elements'] as $idx => $element )
        {
            $id = ( $this->save )( $count > 1 ? $name . ' ' . ( $idx + 1 ) : $name, $language['lang'], $element, $content['fileIds'] );
            $this->saved[$id] = true;

            $refs['elements'][] = ['type' => 'reference', 'refid' => $id, 'group' => $block['group']];
            $refs['elementIds'][] = $id;
        }

        return $refs;
    }


    /**
     * Returns the number of reusable custom blocks with the description in the language.
     *
     * Different blocks with the same description must not share their shared elements. All blocks
     * are counted, also the ones of other domains, so the names don't change if blocks are placed or
     * removed. Untranslated blocks use the description of their default language like in Drupal.
     *
     * @param string $name Block description
     * @param string $langcode Drupal language code
     */
    protected function count( string $name, string $langcode ): int
    {
        if( $this->blockInfos === null )
        {
            $this->blockInfos = [];

            foreach( $this->reader->blockInfos() as $row ) {
                $this->blockInfos[(int) $row->id][$row->default_langcode ? '' : (string) $row->langcode] = mb_strtolower( trim( (string) $row->info ) );
            }
        }

        if( !isset( $this->blockCounts[$langcode] ) )
        {
            $this->blockCounts[$langcode] = [];

            foreach( $this->blockInfos as $infos )
            {
                $info = $infos[$langcode] ?? $infos[''] ?? '';
                $this->blockCounts[$langcode][$info] = ( $this->blockCounts[$langcode][$info] ?? 0 ) + 1;
            }
        }

        return $this->blockCounts[$langcode][mb_strtolower( $name )] ?? 0;
    }


}
