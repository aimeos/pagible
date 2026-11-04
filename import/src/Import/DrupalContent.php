<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Renders the fields of Drupal nodes, paragraphs, custom blocks and media as content elements.
 *
 * Shared custom blocks are created by the importer using the closure.
 */
class DrupalContent
{
    /** @var array<int, string> Field types which aren't content or are imported as meta data */
    protected array $ignoredTypes = ['comment', 'entity_reference:taxonomy_term', 'layout_section', 'metatag', 'path'];


    /**
     * Initializes the renderer.
     *
     * @param DrupalData $data Configuration, entities and field values of the Drupal database
     * @param DrupalUrls $urls URLs of the imported pages
     * @param DrupalSite $site URLs of the Drupal site
     * @param DrupalFiles $files Imports the files of the Drupal site
     * @param DrupalFilters $filters Applies the filters of the Drupal text formats and rewrites the links
     * @param DrupalLayout $layout Reads the Layout Builder sections of the nodes
     * @param DrupalFormatter $formatter Formats the values of simple fields as HTML
     * @param DrupalConverters $converters Converts the HTML to content elements and tracks the removed HTML
     * @param DrupalMedia $media Renders the media entities, files and videos
     * @param \Closure(int, string, bool, int): ?array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} $block Returns the content of the custom block by ID, language code, fallback and depth
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalData $data,
        protected DrupalUrls $urls,
        protected DrupalSite $site,
        protected DrupalFiles $files,
        protected DrupalFilters $filters,
        protected DrupalLayout $layout,
        protected DrupalFormatter $formatter,
        protected DrupalConverters $converters,
        protected DrupalMedia $media,
        protected \Closure $block,
        protected \Closure $warn,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Creates the renderer with the objects which filter, format and convert the fields and render the embedded entities.
     *
     * @param DrupalData $data Configuration, entities and field values of the Drupal database
     * @param DrupalLanguages $languages Languages of the imported pages
     * @param DrupalUrls $urls URLs of the imported pages
     * @param DrupalSite $site URLs of the Drupal site
     * @param DrupalFiles $files Imports the files of the Drupal site
     * @param DrupalMeta $meta Returns the summaries of embedded nodes
     * @param \Closure(int, string, bool, int): ?array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} $block Returns the content of the custom block by ID, language code, fallback and depth
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public static function create( DrupalData $data, DrupalLanguages $languages, DrupalUrls $urls, DrupalSite $site, DrupalFiles $files,
        DrupalMeta $meta, \Closure $block, \Closure $warn, \Closure $warnOnce ): self
    {
        $formatter = new DrupalFormatter( $data, $urls );
        $embeds = null;

        // embedded entities can contain formatted texts with embedded entities again
        $converters = new DrupalConverters( $files, self::embed( $embeds ) );
        $media = new DrupalMedia( $data, $files, $converters, $warnOnce );
        $embeds = new DrupalEmbeds( $data, $files, $meta, $formatter, $media, $converters, $warn, $warnOnce );

        return new self( $data, $urls, $site, $files, new DrupalFilters( $data, $languages, $urls, $site ), new DrupalLayout( $data ),
            $formatter, $converters, $media, $block, $warn, $warnOnce );
    }


    /**
     * Returns the closure which renders the embedded media or entity tag by the renderer assigned to the reference later.
     *
     * @param DrupalEmbeds|null $embeds Reference to the renderer of the embedded media and entities
     * @return \Closure(string, string): ?array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}
     */
    protected static function embed( ?DrupalEmbeds &$embeds ): \Closure
    {
        return function( string $tag, string $langcode ) use ( &$embeds ): ?array {
            return ( $embeds ?? throw new \LogicException( 'Embedded entities are rendered before the renderer is created' ) )->render( $tag, $langcode );
        };
    }


    /**
     * Returns the content of the node from its Layout Builder sections or its fields.
     *
     * @param object{nid: int, type: string} $node
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to use the values of another language if none exist for the language
     * @param int|null $vid Revision ID of the draft which is rendered or NULL for the current revision
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function render( object $node, string $langcode, bool $fallback, ?int $vid = null ): array
    {
        if( ( $sections = $this->layout->sections( $node, $langcode, $vid ) ) !== null ) {
            return $this->renderSections( $sections, $node, $langcode, $fallback, $vid );
        }

        return $this->renderFields( 'node', $node->type, $vid ?? $node->nid, $langcode, $fallback, revision: $vid !== null );
    }


    /**
     * Returns the content of the Layout Builder sections in the order of their regions and components.
     *
     * Supports field blocks of the node, inline blocks and reusable content blocks.
     *
     * @param array<int, array<string, mixed>> $sections Layout Builder sections
     * @param object{nid: int, type: string} $node
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function renderSections( array $sections, object $node, string $langcode, bool $fallback, ?int $vid = null ): array
    {
        $parts = [];
        $components = $this->layout->components( $sections );

        // the inline and reusable blocks of the layout are fetched at once, the ones of custom layouts already with the other nodes
        [$revisions, $uuids] = $this->layout->blocks( $components );

        $this->data->prefetchRevisions( 'block_content', $revisions );
        $this->data->prefetchBlocks( $uuids );

        foreach( $components as $component )
        {
            $config = (array) ( $component['configuration'] ?? [] );
            $plugin = explode( ':', (string) ( $config['id'] ?? '' ) );

            $parts[] = match( $plugin[0] ) {
                'field_block' => ( $plugin[1] ?? '' ) === 'node' ? $this->renderField( 'node', $plugin[3] ?? '', $vid ?? $node->nid,
                    $langcode, $fallback, revision: $vid !== null, bundle: $node->type ) : null,
                'inline_block' => !empty( $config['block_revision_id'] )
                    ? $this->renderFields( 'block_content', $plugin[1] ?? '', (int) $config['block_revision_id'], $langcode, $fallback, revision: true, depth: 1 )
                    : null,
                'block_content' => ( $block = $this->data->entity( 'block_content', 'uuid', $plugin[1] ?? '' ) )
                    ? $this->renderFields( 'block_content', (string) $block->type, (int) $block->id, $langcode, $fallback, depth: 1 )
                    : null,
                default => null,
            };
        }

        return Elements::merge( ...$parts );
    }


    /**
     * Returns the content of the fields of the entity in the order of the default view display.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $bundle Bundle name, e.g. "article"
     * @param int|string $id Entity ID or revision ID if $revision is TRUE
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to use the values of another language if none exist for the language
     * @param bool $revision TRUE if the ID is a revision ID
     * @param int $depth Nesting level of paragraphs and blocks
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function renderFields( string $entityType, string $bundle, int|string $id, string $langcode, bool $fallback, bool $revision = false, int $depth = 0 ): array
    {
        return Elements::merge( ...array_map(
            fn( $field ) => $this->renderField( $entityType, $field, $id, $langcode, $fallback, $revision, $depth, $bundle ),
            $this->data->displayFields( $entityType, $bundle )
        ) );
    }


    /**
     * Returns the content of a field of the entity.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * @param int|string $id Entity ID or revision ID if $revision is TRUE
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to use the values of another language if none exist for the language
     * @param bool $revision TRUE if the ID is a revision ID
     * @param int $depth Nesting level of paragraphs and blocks
     * @param string $bundle Bundle name for the field label and settings, e.g. "article"
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function renderField( string $entityType, string $field, int|string $id, string $langcode, bool $fallback, bool $revision = false, int $depth = 0, string $bundle = '' ): ?array
    {
        if( !( $storage = $this->data->storage( $entityType, $field ) ) || $depth > 10 ) {
            return null;
        }

        $target = (string) ( $storage['settings']['target_type'] ?? '' );
        $rows = $this->data->fieldValues( $entityType, $field, $id, $langcode, $fallback, $revision );
        $parts = $texts = $images = $links = [];

        if( in_array( $storage['type'], ['image', 'file', 'media', 'entity_reference', 'entity_reference_revisions'], true ) ) {
            $this->data->prefetchTargets( $this->data->fieldTable( $entityType, $field, $revision ), $field, in_array( $storage['type'], ['image', 'file', 'media'], true ) ? 'file' : $target );
        } elseif( in_array( $storage['type'], ['text', 'text_long', 'text_with_summary'], true ) ) {
            // media embedded into the texts of all fetched entities are fetched at once
            $this->media->prefetch( $this->data->fetchedRows( $this->data->fieldTable( $entityType, $field, $revision ) ), $field );
        } elseif( $storage['type'] === 'link' ) {
            // terms linked by the fields of all fetched entities are fetched at once
            $this->media->prefetchLinks( $this->data->fetchedRows( $this->data->fieldTable( $entityType, $field, $revision ) ), $field );
        }

        foreach( $rows as $row )
        {
            $value = fn( string $name ) => $row->{$field . '_' . $name} ?? null;

            if( ( $text = $this->formatter->text( $entityType, $bundle, $field, $storage, $value, $langcode ) ) !== null )
            {
                $texts[] = $text;
                continue;
            }

            // files can be hidden per value if the "display" setting of the field is enabled
            if( $storage['type'] === 'file' && !empty( $storage['settings']['display_field'] ) && !(int) ( $value( 'display' ) ?? 1 ) ) {
                continue;
            }

            // links to media are added like media fields so their files are managed in Pagible
            if( ( $mid = $this->linkedMedia( $storage['type'], $value ) ) !== null )
            {
                $parts[] = $this->linkList( $links, $langcode );
                $parts[] = $this->media->render( $this->data->entity( 'media', 'mid', $mid ), $langcode, (string) $value( 'title' ) );
                $links = [];
                continue;
            }

            if( $storage['type'] === 'link' )
            {
                $options = Database::unserialize( $value( 'options' ) );
                $links[] = $this->linkHtml( (string) $value( 'uri' ), (string) $value( 'title' ), $langcode, (array) ( is_array( $options ) ? $options['attributes'] ?? [] : [] ) );
                continue;
            }

            if( ( $name = $this->imageName( $storage['type'], $value ) ) !== null )
            {
                $images[] = $this->files->import( (int) $value( 'target_id' ), $name );
                continue;
            }

            $parts[] = $this->renderValue( $entityType, $field, $storage['type'], $target, $value, $langcode, $fallback, $depth );
        }

        $parts[] = $this->linkList( $links, $langcode );

        $parts[] = $this->renderImages( $images );

        if( $texts = array_filter( $texts, fn( $text ) => $text !== '' ) ) {
            $parts[] = $this->html( $langcode )->text( '<p>' . $this->formatter->label( $entityType, $bundle, $field, $langcode ) . implode( ', ', $texts ) . '</p>' );
        }

        return ( $parts = array_filter( $parts ) ) ? Elements::merge( ...$parts ) : null;
    }


    /**
     * Returns the name of the image if the field value is an image.
     *
     * @param string $type Field type
     * @param \Closure(string): mixed $value Returns the column value of the field by name
     * @return string|null Name of the image or NULL if the value isn't an image
     */
    protected function imageName( string $type, \Closure $value ): ?string
    {
        // the title attribute is used as name if the image has no alternative text
        if( $type === 'image' ) {
            return (string) $value( 'alt' ) ?: (string) $value( 'title' );
        }

        // images of media fields of the Drupal 7 Media module are added like image fields
        if( $type === 'media' && str_starts_with( (string) ( $this->data->fileRow( (int) $value( 'target_id' ) )->filemime ?? '' ), 'image/' ) ) {
            return (string) $value( 'title' );
        }

        return null;
    }


    /**
     * Returns the ID of the media entity the link field value points to.
     *
     * @param string $type Field type
     * @param \Closure(string): mixed $value Returns the column value of the field by name
     * @return int|null Media ID or NULL if the value isn't a link to a media entity
     */
    protected function linkedMedia( string $type, \Closure $value ): ?int
    {
        if( $type === 'link' && preg_match( '/^(?:entity:media\/|internal:\/media\/)(\d+)(?:[?#]|$)/', (string) $value( 'uri' ), $m ) ) {
            return (int) $m[1];
        }

        return null;
    }


    /**
     * Returns the content of the images of a field, several images are shown as slideshow.
     *
     * @param array<int, string|null> $images IDs of the imported image files
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function renderImages( array $images ): ?array
    {
        if( count( $images = array_values( array_unique( array_filter( $images ) ) ) ) > 1 ) {
            return ['elements' => [Elements::create( 'slideshow', ['files' => array_map( fn( $id ) => Elements::file( $id ), $images )] )], 'fileIds' => $images];
        }

        return $images ? Elements::imageContent( $images[0] ) : null;
    }


    /**
     * Returns the content of a field value by the field type.
     *
     * @param string $entityType Drupal entity type
     * @param string $field Field name
     * @param string $type Field type
     * @param string $target Entity type referenced by the field
     * @param \Closure(string): mixed $value Returns the column value of the field by name
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to use the default language if no translation exists
     * @param int $depth Nesting level of the rendered entities
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function renderValue( string $entityType, string $field, string $type, string $target, \Closure $value, string $langcode, bool $fallback, int $depth ): ?array
    {
        return match( $type ) {
            'text', 'text_long', 'text_with_summary' => $this->renderText( (string) $value( 'value' ), (string) $value( 'format' ), $langcode ),
            'string', 'string_long' => $this->html( $langcode )->text( '<p>' . nl2br( e( (string) $value( 'value' ) ), false ) . '</p>' ),
            'address' => $this->renderAddress( $value, $langcode ),
            'video_embed_field' => $this->media->video( (string) $value( 'value' ), $langcode ),
            'file' => $this->media->file( (int) $value( 'target_id' ), (string) $value( 'description' ), $langcode ),
            'media' => $this->media->file( (int) $value( 'target_id' ), (string) $value( 'title' ), $langcode, true ),
            'entity_reference' => match( $target ) {
                'media' => $this->media->render( $this->data->entity( 'media', 'mid', (int) $value( 'target_id' ) ), $langcode ),
                'block_content' => ( $this->block )( (int) $value( 'target_id' ), $langcode, $fallback, $depth + 1 ),
                default => $this->unsupported( "entity_reference:{$target}", "{$entityType}.{$field}" ),
            },
            'entity_reference_revisions' => $target === 'paragraph'
                ? $this->renderParagraph( (int) $value( 'target_id' ), $langcode, $fallback, $depth + 1 )
                : $this->unsupported( "entity_reference_revisions:{$target}", "{$entityType}.{$field}" ),
            default => $this->unsupported( $type, "{$entityType}.{$field}" ),
        };
    }


    /**
     * Returns the content of a published paragraph of the Paragraphs module.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function renderParagraph( int $id, string $langcode, bool $fallback, int $depth ): ?array
    {
        $paragraph = $this->data->entity( 'paragraphs_item_field_data', 'id', $id, $langcode );

        if( !$paragraph || ( isset( $paragraph->status ) && !$paragraph->status ) ) {
            return null;
        }

        return $this->renderFields( 'paragraph', (string) $paragraph->type, $id, $langcode, $fallback, depth: $depth );
    }


    /**
     * Returns the postal address of an Address field as text block.
     *
     * @param \Closure(string): mixed $value Returns the value of the field column, e.g. "locality"
     * @param string $langcode Drupal language code
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function renderAddress( \Closure $value, string $langcode ): ?array
    {
        $str = fn( string $name ) => trim( (string) $value( $name ) );
        $lines = array_filter( [
            trim( $str( 'given_name' ) . ' ' . $str( 'family_name' ) ),
            $str( 'organization' ),
            $str( 'address_line1' ),
            $str( 'address_line2' ),
            trim( $str( 'postal_code' ) . ' ' . $str( 'locality' ) ),
            $str( 'administrative_area' ),
            $str( 'country_code' ),
        ], fn( $line ) => $line !== '' );

        return $lines ? $this->html( $langcode )->text( '<p>' . implode( '<br>', array_map( e( ... ), $lines ) ) . '</p>' ) : null;
    }


    /**
     * Returns the content of a formatted text with links to nodes pointing to the imported pages.
     *
     * @param string $text Text of the field
     * @param string $format Text format ID, e.g. "basic_html"
     * @param string $langcode Drupal language code
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function renderText( string $text, string $format, string $langcode ): array
    {
        $html = $this->filters->apply( $text, $format, $langcode );

        $this->converters->track( $html );

        // paragraphs only containing a link to a file are parsed separately to become file elements
        if( $base = $this->site->base() )
        {
            $pattern = '#<p\b[^>]*>\s*<a\b[^>]*\shref=(["\'])' . preg_quote( $base, '#' ) . '/(?:sites/[^/]+/files|system/files)/[^"\']+\1[^>]*>(?:(?!</a>).)*</a>\s*</p>#is';
            $html = (string) preg_replace( $pattern, "\n\n\$0\n\n", $html );
        }

        return $this->html( $langcode )->elements( $html );
    }


    /**
     * Returns the HTML link of a link field value.
     *
     * @param string $uri Link URI, e.g. "entity:node/42", "internal:/about" or "https://example.com"
     * @param string $title Link text
     * @param string $langcode Drupal language code of the linking content
     * @param array<int|string, mixed> $attributes Link attributes, only "target" with "_blank" and "title" are used
     * @return string HTML link or empty string if the target isn't available
     */
    protected function linkHtml( string $uri, string $title, string $langcode, array $attributes = [] ): string
    {
        $url = match( true ) {
            (bool) preg_match( '/^(?:entity:node\/|internal:\/node\/|route:entity\.node\.canonical;node=)(\d+)(?:[?#;]|$)/', $uri, $m ) => $this->urls->node( (int) $m[1], $langcode ),
            (bool) preg_match( '/^(?:entity:taxonomy_term\/|internal:\/taxonomy\/term\/)(\d+)(?:[?#]|$)/', $uri, $m ) => $this->urls->term( (int) $m[1], $langcode ),
            $uri === 'internal:/<front>' || $uri === 'route:<front>' => '/',
            str_starts_with( $uri, 'internal:/' ) => '/' . ltrim( substr( $uri, 10 ), '/' ),
            str_starts_with( $uri, 'route:' ) || str_starts_with( $uri, 'entity:' ) => null,
            default => $uri,
        };

        $attrs = ( ( $attributes['target'] ?? '' ) === '_blank' ? ' target="_blank"' : '' )
            . ( is_string( $text = $attributes['title'] ?? null ) && trim( $text ) !== '' ? ' title="' . e( trim( $text ) ) . '"' : '' );

        return $url ? '<a href="' . e( $url ) . '"' . $attrs . '>' . e( $title !== '' ? $title : $url ) . '</a>' : '';
    }


    /**
     * Returns the text element with the links, several links are shown as list.
     *
     * @param array<int, string> $links HTML links, empty strings for unavailable targets are skipped
     * @param string $langcode Drupal language code
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function linkList( array $links, string $langcode ): ?array
    {
        if( !( $links = array_values( array_filter( $links ) ) ) ) {
            return null;
        }

        return $this->html( $langcode )->text( count( $links ) > 1 ? '<ul><li>' . implode( '</li><li>', $links ) . '</li></ul>' : '<p>' . $links[0] . '</p>' );
    }


    /**
     * Warns once per field type that fields of this type aren't imported.
     *
     * @param string $type Field type, for entity references including the target type
     * @param string $field Entity type and field name, e.g. "node.field_date"
     * @return null Nothing is imported
     */
    protected function unsupported( string $type, string $field ): null
    {
        if( !in_array( $type, $this->ignoredTypes, true ) ) {
            ( $this->warnOnce )( 'type:' . $type, "  Skipped Drupal fields of type \"{$type}\" (e.g. {$field}), not supported" );
        }

        return null;
    }


    /**
     * Returns the HTML converter which imports the images, embedded entities and files of the Drupal site.
     *
     * @param string $langcode Drupal language code of the converted content, used for the embedded entities
     */
    public function html( string $langcode ): DrupalHtml
    {
        return $this->converters->html( $langcode );
    }


    /**
     * Warns once if HTML which isn't supported by Pagible was removed from the rendered content.
     *
     * @param string $label Label of the rendered node, block or term
     */
    public function removed( string $label ): void
    {
        if( $removed = $this->converters->removed() )
        {
            $tags = implode( ', ', array_map( fn( $tag ) => "<{$tag}>", array_keys( $removed ) ) );
            ( $this->warnOnce )( "removed:{$label}", "  Removed unsupported HTML from {$label}: {$tags}" );
        }
    }
}
