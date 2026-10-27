<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\DB;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Checks the writable languages of the user for items and their latest versions
 * and keeps users with restricted languages from changing the language and domain of pages.
 *
 * @internal
 */
final class Langs
{
    /**
     * Checks if the user may write the languages of the items, of their latest versions and the new language.
     *
     * New versions inherit the language of the latest version and publishing applies it,
     * so both languages must be writable. The languages of latest versions which aren't
     * loaded are only fetched for users with restricted writable languages.
     *
     * @param iterable<mixed>|Model $items Items with "lang" and the "latest" relation or the "latest_id" value
     * @param Authenticatable|null $user Current user
     * @param array<string, mixed> $input Input whose "lang" value is checked if it's passed
     * @throws PermissionException If one of the languages isn't writable
     */
    public static function authorize( iterable|Model $items, ?Authenticatable $user, array $input = [] ) : void
    {
        if( Permission::langs( $user ) === null ) {
            return;
        }

        $latest = [];

        foreach( $items instanceof Model ? [$items] : $items as $item )
        {
            Permission::checkLang( data_get( $item, 'lang' ), $user );

            if( $item instanceof Model && $item->relationLoaded( 'latest' ) )
            {
                $version = $item->getRelation( 'latest' );
                Permission::checkLang( data_get( $version, 'lang' ), $user, $version !== null );
            }
            elseif( $id = data_get( $item, 'latest_id' ) )
            {
                $latest[(string) $id] = true;
            }
        }

        foreach( array_chunk( array_keys( $latest ), DB::IN_SIZE ) as $chunk )
        {
            foreach( Version::whereIn( 'id', $chunk )->pluck( 'lang' ) as $lang ) {
                Permission::checkLang( $lang, $user );
            }
        }

        // an explicit NULL language makes the item language-neutral
        Permission::checkLang( $input['lang'] ?? null, $user, array_key_exists( 'lang', $input ) );
    }


    /**
     * Returns the input of a new page with the domain users with restricted writable languages must use.
     *
     * Languages often map to domains, so restricted users get the domain of the parent page
     * in the same language or the source language and can't choose another one.
     *
     * @param array<string, mixed> $input Validated page input including "lang"
     * @param Authenticatable|null $user Current user
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return array<string, mixed> Page input with the domain of the parent for restricted users
     * @throws PermissionException If a restricted user passes another domain
     */
    public static function domain( array $input, ?Authenticatable $user, ?string $ref, ?string $parent ) : array
    {
        if( Permission::langs( $user ) === null ) {
            return $input;
        }

        $parent ??= $ref ? PageView::withTrashed()->whereKey( $ref )->value( 'parent_id' ) : null;
        $page = $parent ? PageView::withTrashed()->language( (string) $input['lang'] )->whereKey( $parent )->first( ['id', 'domain'] )
            ?? PageView::withTrashed()->whereKey( $parent )->first( ['id', 'domain'] ) : null;
        $domain = (string) $page?->domain;

        // the input is lower case while older stored domains may not be
        Permission::checkUnrestricted( $user, mb_strtolower( (string) ( $input['domain'] ?? $domain ) ) !== mb_strtolower( $domain ) );

        return ['domain' => $domain] + $input;
    }


    /**
     * Checks if users with restricted writable languages keep the language and domain of the page variant.
     *
     * The current values of the variant and its latest version can be repeated. Without a page variant
     * (bulk input for several pages), passing a language or domain requires an unrestricted user.
     *
     * @param array<string, mixed> $input Validated page input
     * @param Authenticatable|null $user Current user
     * @param PageView|null $page Page variant with the "latest" relation loaded or NULL for bulk input
     * @throws PermissionException If the language or domain is changed by a restricted user
     */
    public static function identity( array $input, ?Authenticatable $user, ?PageView $page = null ) : void
    {
        if( Permission::langs( $user ) === null ) {
            return;
        }

        $data = (array) ( $page?->latest->data ?? [] );

        // the domain input is lower case while older stored domains may not be
        $same = fn( string $key, mixed $value ) => $key === 'domain'
            ? mb_strtolower( (string) $value ) === mb_strtolower( (string) $input[$key] )
            : $value === $input[$key];

        foreach( ['lang' => $page?->latest?->lang, 'domain' => $data['domain'] ?? null] as $key => $draft )
        {
            if( array_key_exists( $key, $input ) && ( !$page || !$same( $key, $page->getAttribute( $key ) ) && !$same( $key, $draft ) ) ) {
                Permission::checkUnrestricted( $user );
            }
        }
    }
}
