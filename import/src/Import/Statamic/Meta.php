<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Statamic;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Utils;
use Illuminate\Support\Str;


/**
 * Builds the page title and meta data from the SEO Pro settings and the common SEO fields of Statamic entries.
 */
class Meta
{
    /** Robots directives of SEO Pro which can't be imported */
    protected const UNSUPPORTED_ROBOTS = ['noarchive', 'noimageindex', 'nosnippet'];

    /** Converter for the plain text of Bard fields */
    protected ?Bard $bard = null;


    /**
     * Initializes the meta data builder.
     *
     * @param \Closure(string): ?string $image Imports the asset like "container::path" or its URL and returns the file ID
     * @param string $container Handle of the asset container of plain asset paths
     * @param (\Closure(string, string): void)|null $warn Shows a warning only once for the key
     */
    public function __construct( protected \Closure $image, protected string $container = 'assets', protected ?\Closure $warn = null )
    {
    }


    /**
     * Returns the page title, the meta data and the IDs of the files used by the meta data.
     *
     * SEO Pro stores its settings in the "seo" field of the entry, which is FALSE or contains "enabled: false" if SEO
     * is disabled for the entry.
     * Its values can reference other fields like "@seo:summary" and Antlers templates containing "{{" are skipped
     * because they can't be evaluated. Without SEO Pro, the "meta_*" and "og_*" fields are used.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param string $title Title of the entry used for social media if no other title is set
     * @param array<string, string> $containers Asset containers of the fields from the blueprint by field handle
     * @param array<int, array<string, mixed>> $fallbacks SEO Pro section and site defaults used if referenced fields are empty
     * @return array{title: string|null, meta: array<string, array{type: string, data: array<string, mixed>, files: array<int, string>}>, fileIds: array<int, string>}
     */
    public function build( array $data, string $title, array $containers = [], array $fallbacks = [] ): array
    {
        $seo = $data['seo'] ?? [];

        if( $seo === false || is_array( $seo ) && ( $seo['enabled'] ?? true ) === false ) {
            return ['title' => null, 'meta' => [], 'fileIds' => []];
        }

        $seo = is_array( $seo ) ? $seo : [];

        // Pagible has no element for structured data
        if( $this->warn && $this->value( $seo, 'json_ld_schema', $data, $fallbacks ) !== null ) {
            ( $this->warn )( 'seo:json-ld', 'SEO Pro JSON-LD schemas aren\'t supported and not imported' );
        }

        // values disabled in SEO Pro are FALSE and don't fall back to the other fields
        $plainTitle = $this->value( $seo, 'title', $data, $fallbacks ) ?? ( $this->disabled( $seo, 'title' ) ? null : $this->field( $data, 'meta_title' ) );
        $seoTitle = $this->siteName( $seo, $data, $fallbacks, $plainTitle );
        $description = $this->value( $seo, 'description', $data, $fallbacks ) ?? ( $this->disabled( $seo, 'description' ) ? null : $this->field( $data, 'meta_description' ) ) ?? '';
        $meta = array_filter( ['meta-tags' => Elements::metaTags( $description )] );
        $fileIds = [];

        // SEO Pro uses the SEO title without site name if no Open Graph title is set, the Twitter values are only used for Twitter
        $socialTitle = $this->value( $seo, 'og_title', $data, $fallbacks ) ?? $this->field( $data, 'og_title' );
        $image = $this->value( $seo, 'image', $data, $fallbacks );
        $container = $image === null ? $containers['og_image'] ?? $this->container : $this->container;
        $image ??= $this->field( $data, 'og_image' );
        $fileId = $image !== null && ( $asset = Content::asset( $image, $container ) ) !== '' ? ( $this->image )( $asset ) : null;

        if( $socialTitle !== null || $fileId )
        {
            // SEO Pro has no Open Graph description and uses the meta description for Open Graph
            $socialDescription = $this->field( $data, 'og_description' ) ?? $description;

            $meta['social-media'] = ['type' => 'social-media', 'data' => array_filter( [
                'title' => Str::limit( $socialTitle ?? $plainTitle ?? $seoTitle ?? $title, Elements::SOCIAL_TITLE_SIZE, '…', true ),
                'description' => Str::limit( trim( (string) preg_replace( '/\s+/', ' ', $socialDescription ) ), Elements::DESCRIPTION_SIZE, '…', true ),
                'file' => $fileId ? Elements::file( $fileId ) : null,
            ] ), 'files' => $fileId ? [$fileId] : []];

            $fileIds = $fileId ? [$fileId] : [];
        }

        // relative canonical URLs point to the old site and are left out
        if( ( $url = $this->value( $seo, 'canonical_url', $data, $fallbacks ) ) !== null && preg_match( '#^https?://#i', $url ) && Utils::isValidUrl( $url, strict: false ) ) {
            $meta['canonical'] = ['type' => 'canonical', 'data' => ['url' => $url], 'files' => []];
        }

        $robots = $this->robots( $seo );

        if( $robots && ( $element = Elements::robots( implode( ', ', $robots ) ) ) ) {
            $meta['robots'] = $element;
        }

        return [
            'title' => $seoTitle !== null ? Str::limit( $seoTitle, Elements::TITLE_SIZE, '…', true ) : null,
            'meta' => $meta,
            'fileIds' => $fileIds,
        ];
    }


    /**
     * Returns the trimmed string value of the field or NULL if it's empty or an Antlers template.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param string $key Name of the field
     */
    protected function field( array $data, string $key ): ?string
    {
        $value = $data[$key] ?? null;
        $value = is_array( $value ) && array_is_list( $value ) ? reset( $value ) : $value;

        if( !is_string( $value ) || ( $value = trim( $value ) ) === '' || str_contains( $value, '{{' ) ) {
            return null;
        }

        return $value;
    }


    /**
     * Tests if the SEO Pro value is disabled.
     *
     * @param array<string, mixed> $seo SEO Pro settings of the entry
     * @param string $key Name of the SEO Pro setting
     */
    protected function disabled( array $seo, string $key ): bool
    {
        return ( $seo[$key] ?? null ) === false;
    }


    /**
     * Returns the robots directives from the robots checkboxes, the indexing and following settings and the toggles of SEO Pro.
     *
     * @param array<string, mixed> $seo SEO Pro settings of the entry
     * @return array<int, string> Supported directives like "noindex" and "nofollow"
     */
    protected function robots( array $seo ): array
    {
        // SEO Pro only uses the robots checkboxes if they are set and ignores the other settings then
        if( array_key_exists( 'robots', $seo ) )
        {
            $values = array_map( fn( $value ) => is_array( $value ) ? $value['key'] ?? null : $value, (array) ( $seo['robots'] ?? [] ) );
        }
        else
        {
            $values = [$seo['robots_indexing'] ?? null, $seo['robots_following'] ?? null];

            // older SEO Pro versions store each directive as toggle like "robots_noarchive: true"
            foreach( ['noindex', 'nofollow', ...static::UNSUPPORTED_ROBOTS] as $directive ) {
                $values[] = ( $seo['robots_' . $directive] ?? false ) === true ? $directive : null;
            }
        }

        $values = array_map( fn( $value ) => is_string( $value ) ? strtolower( trim( $value ) ) : '', $values );

        if( $this->warn && ( $unsupported = array_intersect( static::UNSUPPORTED_ROBOTS, $values ) ) ) {
            ( $this->warn )( 'seo:robots', 'SEO Pro robots directives "' . implode( '", "', $unsupported ) . '" aren\'t supported and not imported, only "noindex" and "nofollow"' );
        }

        return array_values( array_intersect( ['noindex', 'nofollow'], $values ) );
    }


    /**
     * Returns the SEO title with the site name of SEO Pro.
     *
     * SEO Pro adds the site name before or after the title separated by the site name separator and uses the
     * site name as title if no SEO title is set.
     *
     * @param array<string, mixed> $seo SEO Pro settings of the entry
     * @param array<string, mixed> $data Decoded entry data
     * @param array<int, array<string, mixed>> $fallbacks SEO Pro section and site defaults used if referenced fields are empty
     * @param string|null $title SEO title or NULL if none is set
     * @return string|null SEO title with site name or NULL if neither is set
     */
    protected function siteName( array $seo, array $data, array $fallbacks, ?string $title ): ?string
    {
        $position = $seo['site_name_position'] ?? 'after';
        $name = $this->value( $seo, 'site_name', $data, $fallbacks );

        if( $title === null || $name === null || !in_array( $position, ['before', 'after'], true ) ) {
            return $title ?? $name;
        }

        $separator = is_string( $seo['site_name_separator'] ?? null ) && trim( $seo['site_name_separator'] ) !== '' ? trim( $seo['site_name_separator'] ) : '|';

        return $position === 'before' ? "{$name} {$separator} {$title}" : "{$title} {$separator} {$name}";
    }


    /**
     * Returns the SEO Pro value, resolving references to other fields like "@seo:summary".
     *
     * If the referenced field is empty, SEO Pro uses the value of the section defaults and then the one of the
     * site defaults instead.
     *
     * @param array<string, mixed> $seo SEO Pro settings of the entry
     * @param string $key Name of the SEO Pro setting
     * @param array<string, mixed> $data Decoded entry data
     * @param array<int, array<string, mixed>> $fallbacks SEO Pro section and site defaults used for empty referenced fields
     */
    protected function value( array $seo, string $key, array $data, array $fallbacks = [] ): ?string
    {
        $value = $seo[$key] ?? null;

        if( !is_string( $value ) || !preg_match( '/^@seo:([\w-]+)$/', trim( $value ), $match ) ) {
            return $this->field( [$key => $value], $key );
        }

        if( ( $result = $this->field( [$key => $this->text( $data[$match[1]] ?? null )], $key ) ) !== null ) {
            return $result;
        }

        foreach( $fallbacks as $defaults )
        {
            if( ( $default = $defaults[$key] ?? null ) !== null && $default !== $value && ( $result = $this->value( [$key => $default], $key, $data ) ) !== null ) {
                return $result;
            }
        }

        return null;
    }


    /**
     * Returns the plain text of a referenced field value.
     *
     * Bard fields like the "content" field which SEO Pro uses for the description by default are stored as
     * ProseMirror nodes, HTML and Markdown text are stripped of their tags.
     *
     * @param mixed $value Value of the referenced field
     * @return mixed Plain text or the value if it's no text
     */
    protected function text( mixed $value ): mixed
    {
        if( is_array( $value ) && array_is_list( $value ) && is_array( $value[0] ?? null ) && isset( $value[0]['type'] ) ) {
            $value = ( $this->bard ??= new Bard( fn( string $href ) => null ) )->text( ['content' => $value] );
        } elseif( is_string( $value ) ) {
            $value = html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        return is_string( $value ) ? trim( (string) preg_replace( '/\s+/', ' ', $value ) ) : $value;
    }
}
