<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;


/**
 * Builds translations of page variants from their source variant.
 *
 * New variants and variants without hashes ("not linked") get a translated copy of the
 * source, existing ones get the changed items of the source merged into their content.
 */
class Sync
{
    /** @var string Editor name of drafts created by AI translations */
    public const EDITOR = 'AI draft';


    /**
     * Returns the translation of the source variant for the target language.
     *
     * The translate callback gets the texts, target and source language plus a context
     * and returns the translated texts in the same order. Without callback, the texts are
     * copied untranslated and their items get empty hashes so they still count as changed.
     *
     * @param Page $source Page with its source variant and the "latest" relation loaded
     * @param Page|null $variant Page with the target variant and the "latest" relation loaded or NULL to create one
     * @param string $lang Language code of the target variant
     * @param (callable(array<int, string>, string, ?string, string): array<int, string>)|null $translate Translate callback
     * @return array{data: array<string, mixed>, aux: array<string, mixed>, hashes: array<string, string>, slug: string, translated: bool}
     */
    public static function translate( Page $source, ?Page $variant, string $lang, ?callable $translate = null ) : array
    {
        [$sdata, $saux] = self::version( $source );
        [$vdata, $vaux] = $variant ? self::version( $variant ) : [[], null];

        $scontent = array_values( (array) ( $saux->content ?? [] ) );
        $smeta = (array) ( $saux->meta ?? [] );
        $sconfig = (array) ( $saux->config ?? [] );

        $shashes = Hashes::page( $sdata, $scontent, $smeta, $sconfig );
        $vhashes = $variant ? (array) $variant->hashes : [];
        $linked = !empty( $vhashes );

        // variants without hashes get a fresh copy of the source
        if( !$linked ) {
            $vaux = (object) [];
        }

        $changed = fn( string $key ) => !$linked || ( $vhashes[$key] ?? null ) !== ( $shashes[$key] ?? null );
        $texts = new Texts();

        // page fields
        $data = $variant ? $vdata : ['lang' => $lang, 'status' => 0] + $sdata;

        foreach( Hashes::PAGE_FIELDS as $field )
        {
            $key = 'page:' . $field;

            if( $changed( $key ) )
            {
                if( array_key_exists( $field, $sdata ) ) {
                    $data[$field] = $sdata[$field];
                } else {
                    unset( $data[$field] );
                }

                if( in_array( $field, ['title', 'name'] ) ) {
                    $texts->add( $key, $data, $field );
                }
            }
        }

        // translated slug of the path for new variants, empty if untranslated
        $slug = ['slug' => $variant ? '' : str_replace( '-', ' ', basename( (string) ( $sdata['path'] ?? '' ) ) )];

        if( !$variant )
        {
            $texts->add( '', $slug, 'slug' );

            if( isset( $data['to'] ) ) {
                $texts->url( $data['to'] );
            }
        }

        $content = self::content( $scontent, array_values( (array) ( $vaux->content ?? [] ) ), $vhashes, $changed, $texts );
        $meta = self::entries( $smeta, (array) ( $vaux->meta ?? [] ), 'meta', $vhashes, $changed, $texts );
        $config = self::entries( $sconfig, (array) ( $vaux->config ?? [] ), 'config', $vhashes, $changed );

        unset( $meta['canonical'] );

        $context = trim( 'Website: ' . config( 'app.name' ) . '. Page: ' . ( $sdata['title'] ?? $sdata['name'] ?? '' ) );
        $translated = $texts->translate( $translate, $lang, $source->lang, $context );
        $texts->links( fn( array $urls ) => self::links( $urls, (string) $source->domain, $lang ) );

        // untranslated texts get empty hashes so they still count as changed
        $hashes = $translated ? $shashes : array_fill_keys( array_diff( $texts->keys(), [''] ), '' ) + $shashes;

        return [
            'data' => $data,
            'aux' => ['content' => $content, 'meta' => (object) $meta, 'config' => (object) $config],
            'hashes' => $hashes,
            'slug' => $translated ? Utils::slugify( (string) $slug['slug'] ) : '',
            'translated' => $translated,
        ];
    }


    /**
     * Returns the content elements of the translation.
     *
     * Elements are arranged in the order and group of the source. Elements added only in the
     * translation stay after their predecessor or at the start, elements deleted only in the
     * translation stay deleted and localized reference elements are kept.
     *
     * @param array<int, \stdClass> $source Content elements of the source variant
     * @param array<int, \stdClass> $variant Content elements of the target variant
     * @param array<string, string> $hashes Hashes the target variant was last synced with
     * @param \Closure(string): bool $changed Tests if the item of the key changed in the source
     * @param Texts $texts Collected texts to translate
     * @return array<int, \stdClass> Content elements of the translation
     */
    protected static function content( array $source, array $variant, array $hashes, \Closure $changed, Texts $texts ) : array
    {
        $mine = $sids = $result = [];

        foreach( $variant as $el ) {
            $mine[(string) ( $el->id ?? '' )] = $el;
        }

        foreach( $source as $el )
        {
            $id = (string) ( $el->id ?? '' );
            $sids[$id] = true;
            $key = 'el:' . $id;
            $own = $mine[$id] ?? null;

            if( !$own && isset( $hashes[$key] ) ) {
                continue; // deleted in the translation
            }

            if( $own && ( !$changed( $key ) || self::localized( $own, $hashes[$key] ?? null ) ) )
            {
                if( property_exists( $el, 'group' ) ) {
                    $own->group = $el->group;
                } else {
                    unset( $own->group );
                }

                $result[] = $own;
                continue;
            }

            $result[] = $copy = self::copy( $el );

            if( ( $copy->type ?? null ) !== 'reference' ) {
                $texts->walk( $key, $copy->data ?? null, Schema::schemas( section: 'content' )[$copy->type ?? ''] ?? [] );
            }
        }

        // elements added only in the translation
        $extra = [];
        $prev = '';
        $kept = array_flip( array_map( fn( $el ) => (string) ( $el->id ?? '' ), $result ) );

        foreach( $mine as $id => $el )
        {
            if( !isset( $sids[$id] ) && !isset( $hashes['el:' . $id] ) ) {
                $extra[$prev][] = $el;
            } elseif( isset( $kept[$id] ) ) {
                $prev = $id;
            }
        }

        $list = $extra[''] ?? [];

        foreach( $result as $el ) {
            array_push( $list, $el, ...( $extra[(string) ( $el->id ?? '' )] ?? [] ) );
        }

        return $list;
    }


    /**
     * Returns the URLs of the same pages in the target language for the URLs of internal pages.
     *
     * URLs of pages without a variant in the target language and external URLs are skipped.
     *
     * @param array<int, string> $urls Absolute URLs or site-relative paths
     * @param string $domain Domain of the source page the relative paths belong to
     * @param string $lang Language code of the target variant
     * @return array<string, string> New URLs by old URL
     */
    protected static function links( array $urls, string $domain, string $lang ) : array
    {
        if( !( $url = Utils::pageUrl( '_path_', '_domain_' ) ) ) {
            return [];
        }

        $multi = (bool) config( 'cms.multidomain' );
        $base = (array) parse_url( $url );
        $prefix = strstr( (string) ( $base['path'] ?? '' ), '_path_', true ) ?: '/';
        $found = [];

        foreach( $urls as $url )
        {
            if( !is_array( $parts = parse_url( $url ) ) || isset( $parts['user'] ) ) {
                continue;
            }

            if( isset( $parts['scheme'], $parts['host'] ) && in_array( strtolower( $parts['scheme'] ), ['http', 'https'], true )
                && ( $multi || strtolower( $parts['host'] ) === strtolower( (string) ( $base['host'] ?? '' ) ) )
            ) {
                $dom = $multi ? strtolower( $parts['host'] ) : '';
            } elseif( !isset( $parts['scheme'] ) && str_starts_with( $url, '/' ) && !str_starts_with( $url, '//' ) ) {
                $dom = $multi ? $domain : '';
            } else {
                continue;
            }

            if( str_starts_with( ( $path = $parts['path'] ?? '/' ) . '/', $prefix ) ) {
                $found[$url] = [$dom, trim( rawurldecode( substr( $path, strlen( $prefix ) ) ), '/' ), $parts];
            }
        }

        if( empty( $found ) ) {
            return [];
        }

        // variants in the target language of the pages the URLs point to, keyed by the linked domain and path
        $table = ( new PageVariant() )->getTable();
        $targets = collect();

        // chunked to stay below the parameter limits of the databases for pages with many links
        foreach( collect( $found )->groupBy( fn( array $item ) => $item[0] ) as $domain => $list )
        {
            foreach( $list->pluck( 1 )->unique()->chunk( 1000 ) as $paths )
            {
                $targets = $targets->merge( PageVariant::join( $table . ' as src', fn( $join ) => $join
                        ->on( 'src.page_id', '=', $table . '.page_id' )
                        ->on( 'src.tenant_id', '=', $table . '.tenant_id' )
                        ->whereNull( 'src.deleted_at' )
                    )
                    ->where( $table . '.lang', $lang )
                    ->where( 'src.domain', (string) $domain )
                    ->whereIn( 'src.path', $paths->values()->all() )
                    ->get( ['src.domain as src_domain', 'src.path as src_path', $table . '.domain', $table . '.path'] )
                    ->keyBy( fn( $v ) => $v->getAttribute( 'src_domain' ) . '/' . $v->getAttribute( 'src_path' ) ) );
            }
        }

        $map = [];

        foreach( $found as $url => [$dom, $path, $parts] )
        {
            if( !( $target = $targets->get( $dom . '/' . $path ) ) ) {
                continue;
            }

            $new = $prefix . $target->path
                . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
                . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );

            // relative paths only stay relative if the target is on the same domain
            if( isset( $parts['host'] ) || $multi && $target->domain !== $dom )
            {
                $host = $multi ? ( $target->domain ?: ( $parts['host'] ?? $dom ) ) : $parts['host'];
                $new = ( $parts['scheme'] ?? $base['scheme'] ?? 'https' ) . '://' . $host
                    . ( isset( $parts['port'] ) && !$multi ? ':' . $parts['port'] : '' ) . $new;
            }

            if( $new !== $url ) {
                $map[$url] = $new;
            }
        }

        return $map;
    }


    /**
     * Returns a deep copy of the value.
     *
     * @param mixed $value Value to copy
     * @return mixed Copied value
     */
    protected static function copy( mixed $value ) : mixed
    {
        return json_decode( (string) json_encode( $value ) );
    }


    /**
     * Returns the meta or config entries of the translation.
     *
     * @param array<string, mixed> $source Entries of the source variant by key
     * @param array<string, mixed> $variant Entries of the target variant by key
     * @param string $kind Kind of the entries, "meta" or "config"
     * @param array<string, string> $hashes Hashes the target variant was last synced with
     * @param \Closure(string): bool $changed Tests if the item of the key changed in the source
     * @param Texts|null $texts Collected texts to translate or NULL to copy the entries unchanged
     * @return array<string, mixed> Entries of the translation by key
     */
    protected static function entries( array $source, array $variant, string $kind, array $hashes,
        \Closure $changed, ?Texts $texts = null ) : array
    {
        $result = $variant;

        foreach( $source as $name => $entry )
        {
            $key = $kind . ':' . $name;

            if( isset( $result[$name] ) ? !$changed( $key ) : isset( $hashes[$key] ) ) {
                continue; // unchanged or deleted in the translation
            }

            $result[$name] = $copy = self::copy( $entry );
            $texts?->walk( $key, $copy->data ?? null, Schema::schemas( section: $kind )[$copy->type ?? $name] ?? [] );
        }

        // entries removed in the source
        foreach( $result as $name => $entry )
        {
            if( !isset( $source[$name] ) && isset( $hashes[$kind . ':' . $name] ) ) {
                unset( $result[$name] );
            }
        }

        return $result;
    }


    /**
     * Tests if a reference element was localized by an editor.
     *
     * @param object $el Content element of the target variant
     * @param string|null $hash Hash of the element when it was last synced
     * @return bool TRUE if the reference element points to another shared element than last synced
     */
    protected static function localized( object $el, ?string $hash ) : bool
    {
        if( ( $el->type ?? null ) !== 'reference' || !$hash ) {
            return false;
        }

        $item = (array) $el;
        unset( $item['id'], $item['group'] );

        return Hashes::hash( $item ) !== $hash;
    }


    /**
     * Returns the data and auxiliary data of the latest version of the page variant.
     *
     * @param Page $page Page with the variant and the "latest" relation loaded
     * @return array{0: array<string, mixed>, 1: object} Version data and copy of the content, meta and config
     */
    protected static function version( Page $page ) : array
    {
        if( $page->latest ) {
            return [(array) $page->latest->data, self::copy( $page->latest->aux ?? new \stdClass() )];
        }

        return [
            $page->only( PageVariant::FIELDS ),
            self::copy( (object) ['content' => $page->content, 'meta' => $page->meta, 'config' => $page->config] )
        ];
    }
}
