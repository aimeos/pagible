<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Translation\Hashes;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Resource\Support\PageRows;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Translation\Sync;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Page variant helpers shared by the importers for translated pages.
 */
class Variants
{
    /**
     * Splits the translated records into the ones of known languages and the codes of the unknown languages.
     *
     * @template T
     * @param Collection<int, T> $rows Translated records
     * @param \Closure(T): (int|string) $code Returns the language code or ID of the record
     * @param \Closure(int|string): bool $known Tests if the language code or ID is known
     * @return array{rows: Collection<int, T>, unknown: array<int, int|string>} Records of known languages and the unknown codes
     */
    public static function known( Collection $rows, \Closure $code, \Closure $known ) : array
    {
        $unknown = $rows->map( $code )->unique()->reject( $known )->values()->all();

        return ['rows' => $rows->filter( fn( $row ) => $known( $code( $row ) ) ), 'unknown' => $unknown];
    }


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
     * Returns the published source variant of the imported page with its content and hashes.
     *
     * The result is used for saving all translations of the page.
     *
     * @param PageView $page Imported page
     * @return array{page: PageView, content: array<int, array<string, mixed>>, hashes: array<string, string>} Source variant, its content elements and hashes
     */
    public static function source( PageView $page ) : array
    {
        // the imported page contains the published source variant after its version has been published
        $source = $page->isSourceVariant() ? $page : PageView::withTrashed()->findOrFail( $page->id );

        return [
            'page' => $source,
            'content' => (array) json_decode( (string) json_encode( $source->content ), true ),
            'hashes' => Hashes::published( $source ),
        ];
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
     * @param PageView $page Imported page with its source variant
     * @param array{lang: string, domain: string} $language Language of the variant
     * @param string $slug Path of the translation
     * @param array<string, mixed> $data Page data of the variant
     * @param array{elements: array<int, array<string, mixed>>, fileIds?: array<string>, elementIds?: array<string>, meta?: array<string, mixed>} $content Translated content and meta data
     * @param string $editor Editor name
     * @param PageView|false|null $variant Variant in the language including trashed ones if already loaded, NULL if there's none or FALSE to load it
     * @param array{page: PageView, content: array<int, array<string, mixed>>, hashes: array<string, string>}|null $prepared Result of source() for the page or NULL to get it
     * @return PageView|null Published page variant or NULL if the URL is used by another page
     */
    public static function save( PageView $page, array $language, string $slug, array $data, array $content, string $editor,
        PageView|false|null $variant = false, ?array $prepared = null ) : ?PageView
    {
        $prepared ??= self::source( $page );
        $source = $prepared['page'];
        $linked = self::pair( $prepared['content'], $content['elements'] );

        $lang = $language['lang'];
        $domain = $language['domain'] !== '' ? $language['domain'] : (string) $source->domain;

        if( $variant === false ) {
            $variant = PageView::withTrashed()->language( $lang, true )->whereKey( $page->id )->first();
        }

        $path = trim( $slug, '/' );

        // URLs are imported as they are, so translations whose URL is taken are skipped,
        // the unique URL of an existing translation can't be used by another one
        if( ( !$variant || (string) $variant->domain !== $domain || (string) $variant->path !== $path ) && PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $path )
            ->when( $variant?->variant_id, fn( $query, $id ) => $query->whereKeyNot( $id ) )->exists()
        ) {
            return null;
        }

        $data = array_replace( $data, ['domain' => $domain, 'path' => $path, 'lang' => $lang] );
        $hashes = $linked !== null ? $prepared['hashes'] : [];

        $aux = ['content' => $linked ?? $content['elements']] + ( !empty( $content['meta'] ) ? ['meta' => $content['meta']] : [] );

        if( $variant )
        {
            if( $restored = $variant->getAttribute( 'variant_deleted_at' ) !== null )
            {
                PageVariant::withTrashed()->whereKey( $variant->variant_id )->update( ['deleted_at' => null] );
                $variant->forceFill( ['variant_deleted_at' => null] );
            }

            // repeated imports don't write the translation state if it's unchanged
            if( (bool) $variant->stale !== ( $linked === null ) || Hashes::stale( $hashes, (array) $variant->hashes ) ) {
                $variant = Sync::state( $variant, $hashes, $linked === null );
            }

            // repeated imports don't create new versions of unchanged translations, restored ones are published and indexed again
            if( !$restored && Pages::unchanged( $variant, $data, $aux ) ) {
                return $variant;
            }
        }
        else
        {
            $variant = PageRows::insert( $source, $data + ['status' => 1], $hashes, $linked === null, $editor );
        }

        Pages::publish( $variant, $data, $aux, $content['fileIds'] ?? [], $content['elementIds'] ?? [], $lang, $editor );

        // the other variants aren't listed for the language of the new variant any more
        Scout::variants( [(string) $page->id], [$lang] );

        return $variant;
    }
}
