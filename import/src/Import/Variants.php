<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Hashes;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;


/**
 * Page variant helpers shared by the importers for translated pages.
 */
class Variants
{
    /**
     * Returns the language code with the domain from its base.
     *
     * The base is an URL like "https://example.de/" if the variants are on a separate domain.
     *
     * @param string $code Language code
     * @param string|null $base URL of the language or NULL for the domain of the source pages
     * @return array{lang: string, domain: string} Language code and domain or an empty string for the source domain
     * @throws \InvalidArgumentException If the base isn't an URL with a host
     */
    public static function language( string $code, ?string $base = null ) : array
    {
        if( $base === null ) {
            return ['lang' => $code, 'domain' => ''];
        }

        if( !is_string( $host = parse_url( $base, PHP_URL_HOST ) ) || $host === '' ) {
            throw new \InvalidArgumentException( "Invalid language URL: {$base}" );
        }

        return ['lang' => $code, 'domain' => $host];
    }


    /**
     * Returns the translated elements with the IDs of the source elements if both match.
     *
     * @param array<int, array<string, mixed>> $source Content elements of the source variant
     * @param array<int, array<string, mixed>> $translation Translated content elements
     * @return array<int, array<string, mixed>>|null Translated elements or NULL if count, types or order differ
     */
    public static function pair( array $source, array $translation ) : ?array
    {
        $source = array_values( $source );
        $translation = array_values( $translation );

        if( count( $source ) !== count( $translation ) ) {
            return null;
        }

        foreach( $translation as $idx => $element )
        {
            if( ( $source[$idx]['type'] ?? null ) !== ( $element['type'] ?? null ) ) {
                return null;
            }

            if( isset( $source[$idx]['id'] ) ) {
                $translation[$idx]['id'] = $source[$idx]['id'];
            }
        }

        return $translation;
    }


    /**
     * Tests if the path is already used by another page variant in the domain.
     *
     * @param string $domain Domain of the variant
     * @param string $path Path to test
     * @param string|null $variantId ID of the variant which may keep its path
     * @return bool TRUE if the path is used
     */
    public static function used( string $domain, string $path, ?string $variantId = null ) : bool
    {
        return PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $path )
            ->when( $variantId, fn( $query ) => $query->whereKeyNot( $variantId ) )->exists();
    }


    /**
     * Creates or updates the variant of the page in the given language and publishes it.
     *
     * The domain and path of the imported translation are used as they are. If another page
     * already uses them, the translation isn't imported.
     *
     * If the translated elements match the published source elements in count, types and
     * order, they get the IDs of the source elements and the variant starts up to date.
     * Otherwise, the variant isn't linked to the source, so its hashes stay empty and it
     * needs an update.
     *
     * @param Page $page Imported page with its source variant
     * @param array{lang: string, domain: string} $language Language of the variant
     * @param string $slug Path of the translation
     * @param array<string, mixed> $data Page data of the variant
     * @param array{elements: array<int, array<string, mixed>>, fileIds?: array<string>, elementIds?: array<string>} $content Translated content
     * @param string $editor Editor name
     * @return Page|null Published page variant or NULL if the URL is used by another page
     */
    public static function save( Page $page, array $language, string $slug, array $data, array $content, string $editor ) : ?Page
    {
        /** @var Page $source */
        $source = Page::withTrashed()->findOrFail( $page->id );
        $linked = self::pair( (array) json_decode( (string) json_encode( $source->content ), true ), $content['elements'] );

        $lang = $language['lang'];
        $domain = $language['domain'] !== '' ? $language['domain'] : (string) $source->domain;

        /** @var Page|null $variant */
        $variant = Page::withTrashed()->language( $lang, true )->find( $page->id );

        $path = trim( $slug, '/' );

        // URLs are imported as they are, so translations whose URL is taken are skipped
        if( self::used( $domain, $path, $variant?->variant_id ) ) {
            return null;
        }

        $data = array_replace( $data, ['domain' => $domain, 'path' => $path, 'lang' => $lang] );

        if( !$variant )
        {
            $new = new PageVariant();
            $new->forceFill( array_intersect_key( $data, array_flip( ['to', 'name', 'title', 'theme', 'tag'] ) ) + [
                'page_id' => $page->id,
                'lang' => $lang,
                'domain' => $domain,
                'path' => $path,
                'status' => $data['status'] ?? 1,
                'editor' => $editor,
            ] )->save();

            $variant = Page::variant( (string) $new->id )->firstOrFail();
        }
        elseif( $variant->getAttribute( 'variant_deleted_at' ) !== null )
        {
            PageVariant::withTrashed()->findOrFail( $variant->variant_id )->restore();
            $variant = Page::variant( $variant->variant_id )->firstOrFail();
        }

        $aux = ['content' => $linked ?? $content['elements']];
        Pages::publish( $variant, $data, $aux, $content['fileIds'] ?? [], $content['elementIds'] ?? [], $lang, $editor );

        $hashes = $linked !== null
            ? Hashes::page( $source->only( Hashes::PAGE_FIELDS ), $source->content, $source->meta, $source->config )
            : [];

        PageVariant::whereKey( $variant->variant_id )->update( [
            'hashes' => json_encode( (object) $hashes ),
            'stale' => $linked === null,
        ] );

        return $variant;
    }


    /**
     * Tests if the value is a valid language code like "de" or "zh-Hant".
     *
     * @param string $code Language code
     * @return bool TRUE if the code is valid
     */
    public static function valid( string $code ) : bool
    {
        return strlen( $code ) <= 10 && preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $code ) === 1;
    }
}
