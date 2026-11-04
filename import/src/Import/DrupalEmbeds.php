<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Renders the media and entities embedded into the formatted texts of the Drupal site.
 */
class DrupalEmbeds
{
    /**
     * Initializes the object.
     *
     * @param DrupalData $data Entities of the Drupal database
     * @param DrupalFiles $files Imports the files of the Drupal site
     * @param DrupalMeta $meta Returns the summaries of embedded nodes
     * @param DrupalFormatter $formatter Returns the links to the pages of embedded nodes
     * @param DrupalMedia $media Renders the embedded media entities
     * @param DrupalConverters $converters Converts the HTML of the embedded node links to content elements
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalData $data,
        protected DrupalFiles $files,
        protected DrupalMeta $meta,
        protected DrupalFormatter $formatter,
        protected DrupalMedia $media,
        protected DrupalConverters $converters,
        protected \Closure $warn,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Returns the content of a media entity embedded by the <drupal-media> tag or of an entity embedded by the <drupal-entity> tag.
     *
     * @param string $tag Opening <drupal-media> or <drupal-entity> tag
     * @param string $langcode Drupal language code of the embedding content
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    public function render( string $tag, string $langcode ): ?array
    {
        if( !preg_match( '/^<drupal-media\b/i', $tag ) ) {
            return $this->entity( $tag, $langcode );
        }

        $uuid = DrupalHtml::attribute( $tag, 'data-entity-uuid' );
        $media = $this->data->entity( 'media', 'uuid', $uuid );

        if( !$media ) {
            ( $this->warn )( "  Skipped embedded media {$uuid} (not found)" );
            return null;
        }

        return $this->media->render( $media, $langcode, DrupalHtml::attribute( $tag, 'alt' ), DrupalHtml::caption( $tag ) );
    }


    /**
     * Returns the content of an entity embedded by the Entity Embed module.
     *
     * Media and files become the matching elements, nodes links to their pages with their summary
     * unless they are embedded as label only. Other entities
     * like webforms or blocks are skipped because they have no equivalent.
     *
     * @param string $tag Opening <drupal-entity> tag
     * @param string $langcode Drupal language code of the embedding content
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function entity( string $tag, string $langcode ): ?array
    {
        $type = DrupalHtml::attribute( $tag, 'data-entity-type' );
        $uuid = DrupalHtml::attribute( $tag, 'data-entity-uuid' );
        $id = DrupalHtml::attribute( $tag, 'data-entity-id' );

        [$table, $key] = match( $type ) {
            'media' => ['media', 'mid'],
            'node' => ['node', 'nid'],
            'file' => ['file_managed', 'fid'],
            default => [null, null],
        };

        if( !$table || !$key )
        {
            ( $this->warnOnce )( "embed:{$type}", "  Skipped embedded Drupal {$type} entities, not supported" );
            return null;
        }

        if( !( $entity = $uuid !== '' ? $this->data->entity( $table, 'uuid', $uuid ) : ( ctype_digit( $id ) ? $this->data->entity( $table, $key, (int) $id ) : null ) ) )
        {
            ( $this->warn )( "  Skipped embedded {$type} " . ( $uuid ?: $id ) . ' (not found)' );
            return null;
        }

        $alt = DrupalHtml::attribute( $tag, 'alt' );

        if( $type === 'media' ) {
            return $this->media->render( $entity, $langcode, $alt, DrupalHtml::caption( $tag ) );
        }

        if( $type === 'node' )
        {
            if( ( $link = $this->formatter->nodeLink( (int) $entity->nid, $langcode ) ) === '' ) {
                return null;
            }

            // nodes embedded with a view mode like "teaser" show their summary below the title
            $display = DrupalHtml::attribute( $tag, 'data-entity-embed-display' );
            $summary = str_contains( $display, 'label' ) ? '' : $this->meta->summary( (int) $entity->nid, $langcode );

            return $this->converters->html( $langcode )->text( $summary !== '' ? '<h3>' . $link . '</h3><p>' . e( $summary ) . '</p>' : '<p>' . $link . '</p>' );
        }

        if( !( $fileId = $this->files->import( (int) $entity->fid, $alt ) ) ) {
            return null;
        }

        return DrupalFiles::content( $fileId, (string) ( $entity->filemime ?? '' ), DrupalHtml::caption( $tag ) );
    }
}
