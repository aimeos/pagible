<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;


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

    /** @var list<string> Field types containing text to translate */
    public const TEXT_TYPES = ['string', 'text', 'plaintext', 'markdown'];


    /**
     * Returns the hashes of the latest version of the source variant.
     *
     * @param Page $source Page with its source variant and the "latest" relation loaded
     * @return array<string, string> Hashes of the page fields, content elements, meta and config entries
     */
    public static function hashes( Page $source ) : array
    {
        [$data, $aux] = self::version( $source );

        return Hashes::page( $data, array_values( (array) ( $aux->content ?? [] ) ), (array) ( $aux->meta ?? [] ), (array) ( $aux->config ?? [] ) );
    }


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
        [$vdata, $vaux] = $variant ? self::version( $variant ) : [[], (object) []];

        $scontent = array_values( (array) ( $saux->content ?? [] ) );
        $smeta = (array) ( $saux->meta ?? [] );
        $sconfig = (array) ( $saux->config ?? [] );

        $shashes = Hashes::page( $sdata, $scontent, $smeta, $sconfig );
        $vhashes = $variant ? (array) $variant->hashes : [];
        $linked = !empty( $vhashes );

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

        // slug of the path for new variants
        $slug = ['slug' => str_replace( '-', ' ', basename( (string) ( $sdata['path'] ?? '' ) ) )];

        if( !$variant ) {
            $texts->add( '', $slug, 'slug' );
        }

        $content = self::content( $scontent, array_values( (array) ( $vaux->content ?? [] ) ), $vhashes, $linked, $changed, $texts );
        $meta = self::entries( $smeta, (array) ( $vaux->meta ?? [] ), 'meta', $vhashes, $linked, $changed, $texts );
        $config = self::entries( $sconfig, (array) ( $vaux->config ?? [] ), 'config', $vhashes, $linked, $changed );

        unset( $meta['canonical'] );

        $context = trim( 'Website: ' . config( 'app.name' ) . '. Page: ' . ( $sdata['title'] ?? $sdata['name'] ?? '' ) );
        $translated = $texts->translate( $translate, $lang, $source->lang, $context );

        $hashes = $shashes;

        foreach( $texts->keys() as $key )
        {
            if( !$translated && $key !== '' ) {
                $hashes[$key] = '';
            }
        }

        return [
            'data' => $data,
            'aux' => ['content' => $content, 'meta' => (object) $meta, 'config' => (object) $config],
            'hashes' => $hashes,
            'slug' => Utils::slugify( (string) $slug['slug'] ),
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
     * @param bool $linked TRUE if the target variant has hashes to merge with
     * @param \Closure(string): bool $changed Tests if the item of the key changed in the source
     * @param Texts $texts Collected texts to translate
     * @return array<int, \stdClass> Content elements of the translation
     */
    protected static function content( array $source, array $variant, array $hashes, bool $linked, \Closure $changed, Texts $texts ) : array
    {
        $mine = $sids = $result = [];

        foreach( $linked ? $variant : [] as $el ) {
            $mine[(string) ( $el->id ?? '' )] = $el;
        }

        foreach( $source as $el )
        {
            $id = (string) ( $el->id ?? '' );
            $sids[$id] = true;
            $key = 'el:' . $id;
            $own = $mine[$id] ?? null;

            if( !$own && $linked && isset( $hashes[$key] ) ) {
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
     * @param bool $linked TRUE if the target variant has hashes to merge with
     * @param \Closure(string): bool $changed Tests if the item of the key changed in the source
     * @param Texts|null $texts Collected texts to translate or NULL to copy the entries unchanged
     * @return array<string, mixed> Entries of the translation by key
     */
    protected static function entries( array $source, array $variant, string $kind, array $hashes, bool $linked,
        \Closure $changed, ?Texts $texts = null ) : array
    {
        $result = $linked ? $variant : [];

        foreach( $source as $name => $entry )
        {
            $key = $kind . ':' . $name;

            if( $linked && ( isset( $result[$name] ) ? !$changed( $key ) : isset( $hashes[$key] ) ) ) {
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
            $page->only( [...Hashes::PAGE_FIELDS, 'path', 'domain', 'to', 'status'] ),
            self::copy( (object) ['content' => $page->content, 'meta' => $page->meta, 'config' => $page->config] )
        ];
    }
}
