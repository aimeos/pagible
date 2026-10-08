<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Hashes;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;


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
     * Returns the languages from the --language option values like "key:code[:url]" keyed by their key.
     *
     * @param array<mixed> $values Option values
     * @param string $system Name of the imported system used in the error message
     * @param string $keyPattern Regular expression the key must match
     * @return array<string, array{lang: string, domain: string}> Languages keyed by the key of the values
     * @throws \InvalidArgumentException If a value is invalid
     */
    public static function options( array $values, string $system, string $keyPattern = '/^.+$/sD' ) : array
    {
        $languages = [];

        foreach( $values as $value )
        {
            $parts = explode( ':', is_string( $value ) ? $value : '', 3 );

            if( count( $parts ) < 2 || !preg_match( $keyPattern, $parts[0] ) || !Utils::isValidLang( $parts[1] ) ) {
                throw new \InvalidArgumentException( "Invalid {$system} language: {$value}" );
            }

            $languages[$parts[0]] = self::language( $parts[1], $parts[2] ?? null );
        }

        return $languages;
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
        if( PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $path )
            ->when( $variant?->variant_id, fn( $query, $id ) => $query->whereKeyNot( $id ) )->exists()
        ) {
            return null;
        }

        $data = array_replace( $data, ['domain' => $domain, 'path' => $path, 'lang' => $lang] );
        $hashes = $linked !== null ? Hashes::published( $source ) : [];

        if( $variant )
        {
            PageVariant::withTrashed()->whereKey( $variant->variant_id )->update( [
                'deleted_at' => null,
                'hashes' => json_encode( (object) $hashes ),
                'stale' => $linked === null,
            ] );

            $variant->forceFill( ['variant_deleted_at' => null, 'hashes' => $hashes, 'stale' => $linked === null] )->syncOriginal();
        }
        else
        {
            $variant = Resource::insertVariant( $source, $data + ['status' => 1], $hashes, $linked === null, $editor );
        }

        $aux = ['content' => $linked ?? $content['elements']];
        Pages::publish( $variant, $data, $aux, $content['fileIds'] ?? [], $content['elementIds'] ?? [], $lang, $editor );

        // the source variant isn't listed for the language of the new variant any more
        Scout::sources( [(string) $page->id] );

        return $variant;
    }
}
