<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Str;


/**
 * Imports the meta data of the Drupal nodes from the Metatag module, the body summary and the taxonomy terms.
 */
class DrupalMeta
{
    /** @var array<int, string> Titles of the drafts which are rendered by node ID */
    protected array $titles = [];


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration and field values of the Drupal database
     * @param DrupalFiles $files Imports the images of the meta tags
     */
    public function __construct( protected DrupalData $data, protected DrupalFiles $files )
    {
    }


    /**
     * Executes the function while the title of the node is the one of its draft revision.
     *
     * @template T
     * @param int $nid Node ID
     * @param string|null $title Title of the draft for tokens like "[node:title]" or NULL for the node title
     * @param \Closure(): T $fn Function to execute
     * @return T Result of the function
     */
    public function title( int $nid, ?string $title, \Closure $fn ): mixed
    {
        if( $title !== null ) {
            $this->titles[$nid] = $title;
        }

        try {
            return $fn();
        } finally {
            unset( $this->titles[$nid] );
        }
    }


    /**
     * Builds the meta data of the node from the Metatag field, the body summary and the taxonomy terms.
     *
     * Uses the description and keywords, the Open Graph title, description and image, the canonical
     * URL and the robots settings of the Metatag module.
     *
     * @param object{nid: int, type: string, title: string} $node
     * @param string $langcode Drupal language code of the content
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     * @return array<string, array{type: string, data: array<string, mixed>, files: array<int, string>}> Meta data keyed by type
     */
    public function build( object $node, string $langcode, bool $fallback ): array
    {
        $tags = $this->tags( $node, $langcode );
        $summary = $this->data->nodeValues( 'body', $node->nid, $langcode, $fallback )->first()->body_summary ?? '';
        $description = $tags['description'] ?? trim( html_entity_decode( strip_tags( (string) $summary ), ENT_QUOTES, 'UTF-8' ) );
        $description = Str::limit( (string) preg_replace( '/\s+/', ' ', $description ), 159, '…', true );
        $keywords = $tags['keywords'] ?? implode( ', ', $this->terms( $node, $langcode, $fallback ) );
        $meta = [];

        if( $description !== '' ) {
            $meta['meta-tags'] = ['type' => 'meta-tags', 'data' => array_filter( ['description' => $description, 'keywords' => Str::limit( $keywords, 254, '…', true )] ), 'files' => []];
        }

        // Twitter card tags are used if the Open Graph tags are missing
        $image = $tags['og_image'] ?? $tags['og_image_url'] ?? $tags['og_image_secure_url'] ?? $tags['twitter_cards_image'] ?? '';
        $fileId = $image !== '' ? $this->files->meta( $image ) : null;
        $title = $tags['og_title'] ?? $tags['twitter_cards_title'] ?? null;

        if( $title !== null || $fileId )
        {
            $meta['social-media'] = ['type' => 'social-media', 'data' => array_filter( [
                'title' => Str::limit( $title ?? $tags['title'] ?? $this->titles[$node->nid] ?? $node->title, 59, '…', true ),
                'description' => Str::limit( (string) preg_replace( '/\s+/', ' ', $tags['og_description'] ?? $tags['twitter_cards_description'] ?? $description ), 159, '…', true ),
                'file' => $fileId ? Elements::file( $fileId ) : null,
            ] ), 'files' => $fileId ? [$fileId] : []];
        }

        if( ( $url = $tags['canonical_url'] ?? '' ) !== '' && ( str_starts_with( $url, '/' ) || filter_var( $url, FILTER_VALIDATE_URL ) ) ) {
            $meta['canonical'] = ['type' => 'canonical', 'data' => ['url' => $url], 'files' => []];
        }

        $robots = array_map( 'trim', explode( ',', strtolower( $tags['robots'] ?? '' ) ) );
        $data = array_filter( [
            'index' => in_array( 'noindex', $robots, true ) ? 'noindex' : ( in_array( 'index', $robots, true ) ? 'index' : null ),
            'follow' => in_array( 'nofollow', $robots, true ) ? 'nofollow' : ( in_array( 'follow', $robots, true ) ? 'follow' : null ),
        ] );

        if( $data ) {
            $meta['robots'] = ['type' => 'robots', 'data' => $data, 'files' => []];
        }

        return $meta;
    }


    /**
     * Returns the Metatag values of the node with resolved tokens.
     *
     * The values of the node override the defaults of its content type, which override the defaults
     * of all nodes and the global defaults like in Drupal. Values with tokens which can't be resolved,
     * e.g. "[node:url]", are left out.
     *
     * @param object{nid: int, type: string, title: string} $node
     * @return array<string, string> Meta tag values keyed by name, e.g. "description"
     */
    public function tags( object $node, string $langcode ): array
    {
        $tags = [];

        foreach( ['global', 'node', 'node__' . $node->type] as $name ) {
            $tags = array_replace( $tags, $this->values( $this->data->config( 'metatag.metatag_defaults.' . $name, $langcode )['tags'] ?? null ) );
        }

        $fields = array_filter( $this->data->entityFields( 'node', $node->type ), fn( $field ) => ( $this->data->storage( 'node', $field )['type'] ?? '' ) === 'metatag' );

        if( !$fields && $this->data->hasTable( 'node__field_meta_tags' ) ) {
            $fields = ['field_meta_tags'];
        }

        foreach( $fields as $field )
        {
            $value = (string) ( $this->data->nodeValues( $field, $node->nid, $langcode )->first()->{$field . '_value'} ?? '' );

            // Metatag 2.x stores JSON, 1.x serialized arrays
            $values = json_decode( $value, true );
            $values = is_array( $values ) ? $values : Database::unserialize( $value );

            if( is_array( $values ) )
            {
                $tags = array_replace( $tags, $this->values( $values ) );
                break;
            }
        }

        $tags = array_map( fn( $tag ) => trim( (string) preg_replace_callback( '/\[([\w-]+(?::[\w-]+)+)\]/', fn( $m ) => $this->token( $m[1], $node, $langcode ) ?? $m[0], $tag ) ), $tags );

        return array_filter( $tags, fn( $tag ) => $tag !== '' && !preg_match( '/\[[\w-]+:[^\]]+\]/', $tag ) );
    }


    /**
     * Returns the non-empty scalar meta tag values as strings.
     *
     * @param mixed $values Meta tag values keyed by name
     * @return array<string, string>
     */
    protected function values( mixed $values ): array
    {
        $tags = [];

        foreach( is_array( $values ) ? $values : [] as $name => $value )
        {
            if( is_scalar( $value ) && trim( (string) $value ) !== '' ) {
                $tags[(string) $name] = (string) $value;
            }
        }

        return $tags;
    }


    /**
     * Returns the value of the Drupal token used in meta tags or NULL if it isn't supported.
     *
     * Supports the title and summary of the node, the site name and slogan and the URLs of the
     * files in image and file fields, e.g. "[node:field_image:entity:url]".
     *
     * @param string $token Token without brackets, e.g. "node:title"
     * @param object{nid: int, title: string} $node
     */
    protected function token( string $token, object $node, string $langcode ): ?string
    {
        $parts = explode( ':', $token );

        switch( true )
        {
            case $token === 'node:title' || $token === 'current-page:title':
                return $this->titles[$node->nid] ?? $node->title;
            case $token === 'node:summary' || $token === 'node:body:summary':
                return $this->summary( $node->nid, $langcode );
            case $token === 'site:name' || $token === 'site:slogan':
                return ( $value = $this->data->config( 'system.site', $langcode )[$parts[1] === 'name' ? 'name' : 'slogan'] ?? '' ) ? (string) $value : null;
            case $parts[0] === 'node' && str_starts_with( $parts[1], 'field_' )
                && in_array( implode( ':', array_slice( $parts, 2 ) ), ['', 'url', 'entity:url', '0:url', '0:entity:url'], true ):
                $row = $this->data->nodeValues( $parts[1], $node->nid, $langcode, true )->first();
                $file = $this->data->fileRow( (int) ( $row->{$parts[1] . '_target_id'} ?? 0 ) );
                return $file ? $this->files->url( (string) ( $file->uri ?? '' ) ) : null;
        }

        return null;
    }


    /**
     * Returns the summary of the node body or the beginning of its text like Drupal's "[node:summary]" token.
     */
    public function summary( int $nid, string $langcode ): string
    {
        $row = $this->data->nodeValues( 'body', $nid, $langcode, true )->first();
        $text = trim( (string) ( $row->body_summary ?? '' ) ) ?: (string) ( $row->body_value ?? '' );
        $text = strip_tags( (string) preg_replace( '/<(?:p|br|div|h[1-6]|li|td|th)\b/i', ' $0', $text ) );
        $text = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );

        return Str::limit( $text, 300, '…', true );
    }


    /**
     * Returns the names of the taxonomy terms referenced by the node.
     *
     * @param object{nid: int, type: string} $node
     * @return array<int, string> Term names
     */
    protected function terms( object $node, string $langcode, bool $fallback ): array
    {
        $fields = array_filter( $this->data->entityFields( 'node', $node->type ), function( $field ) {
            $storage = $this->data->storage( 'node', $field );
            return ( $storage['type'] ?? '' ) === 'entity_reference' && ( $storage['settings']['target_type'] ?? '' ) === 'taxonomy_term';
        } ) ?: ['field_tags'];

        $names = [];

        foreach( $fields as $field )
        {
            $rows = $this->data->nodeValues( $field, $node->nid, $langcode, $fallback );
            $this->data->prefetchTargets( $this->data->fieldTable( 'node', $field ), $field, 'taxonomy_term' );

            foreach( $rows as $row )
            {
                if( $term = $this->data->entity( 'taxonomy_term_field_data', 'tid', (int) ( $row->{$field . '_target_id'} ?? 0 ), $langcode ) ) {
                    $names[] = (string) $term->name;
                }
            }
        }

        return array_values( array_unique( $names ) );
    }
}
