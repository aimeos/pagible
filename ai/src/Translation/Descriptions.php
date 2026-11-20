<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Translation;

use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Exceptions\ThrottledException;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;


/**
 * Translates file descriptions within the rate limit of the tenant.
 *
 * @phpstan-type Todo array<string, array<string, list<array{0: string, 1: string}>>>
 */
final class Descriptions
{
    /**
     * Translates the descriptions of the files into the languages they are missing in and saves them as file drafts.
     *
     * Calls count against the tenant's "cms.ai.ratelimit", translations done before exceeding it are saved.
     *
     * @param array<int, string> $ids File IDs
     * @param array<int, string> $langs Target language codes
     * @param Authenticatable|null $user User translating the files or NULL inside Permission::system()
     * @return array<int, File> Files with new drafts, missing and trashed files or files without anything to translate are skipped
     * @throws PermissionException If the user isn't allowed to translate texts
     * @throws ThrottledException If the rate limit of the tenant would be exceeded
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function translate( array $ids, array $langs, ?Authenticatable $user ) : array
    {
        Permission::check( 'text:translate', $user );

        $files = File::with( 'latest' )->whereIn( 'id', array_unique( $ids ) )->get()->keyBy( 'id' );
        $todo = self::missing( $files, array_unique( $langs ) );
        $result = [];

        try
        {
            foreach( self::texts( $todo ) as $to => $texts )
            {
                foreach( $texts as $id => $text ) {
                    $result[$id][$to] = $text;
                }
            }
        }
        catch( \Throwable $e )
        {
            // keep charged translations, the translation error is more relevant than a failed save
            rescue( fn() => self::save( $files, $result, $user ) );
            throw $e;
        }

        return self::save( $files, $result, $user );
    }


    /**
     * Returns the non-empty descriptions of the file draft.
     *
     * @param File $file File with the latest version loaded
     * @return array<string, string> Descriptions by language code
     */
    private static function descriptions( File $file ) : array
    {
        $desc = (array) $file->latestAux( 'description' );
        return array_filter( $desc, fn( $v ) => is_string( $v ) && trim( $v ) !== '' );
    }


    /**
     * Returns the descriptions which must be translated.
     *
     * @param Collection<string, File> $files Files by ID
     * @param array<int, string> $langs Target language codes
     * @return Todo File ID and text pairs by target and source language
     */
    private static function missing( Collection $files, array $langs ) : array
    {
        $locale = app()->getLocale();
        $todo = [];

        foreach( $files as $file )
        {
            $desc = self::descriptions( $file );

            if( !$desc ) {
                continue;
            }

            // file language first, then the editor's language
            $from = (string) $file->lang;

            if( !isset( $desc[$from] ) ) {
                $from = isset( $desc[$locale] ) ? $locale : (string) array_key_first( $desc );
            }

            foreach( $langs as $lang )
            {
                if( !isset( $desc[$lang] ) ) {
                    $todo[$lang][$from][] = [(string) $file->id, $desc[$from]];
                }
            }
        }

        return $todo;
    }


    /**
     * Saves the translated descriptions of the files.
     *
     * @param Collection<string, File> $files Files by ID
     * @param array<string, array<string, string>> $texts Translated descriptions by file ID and language
     * @param Authenticatable|null $user User saving the files
     * @return array<int, File> Saved files
     */
    private static function save( Collection $files, array $texts, ?Authenticatable $user ) : array
    {
        $saved = [];

        // missing() only returns loaded files
        foreach( $files->toBase()->only( array_keys( $texts ) ) as $id => $file ) {
            $saved[] = Files::save( $id, ['description' => self::descriptions( $file ) + $texts[$id]], $user, $file->latest_id );
        }

        return $saved;
    }


    /**
     * Translates the descriptions into each target language.
     *
     * Equal texts are translated once, results per source language survive a later failure.
     *
     * @param Todo $todo File ID and text pairs by target and source language
     * @return \Generator<string, array<string, string>> Translated descriptions by file ID for each target and source language
     */
    private static function texts( array $todo ) : \Generator
    {
        foreach( $todo as $to => $sources )
        {
            foreach( $sources as $from => $pairs )
            {
                $result = [];
                $texts = self::unique( $pairs );
                // same order or an exception
                $map = array_combine( $texts, Translator::translate( $texts, (string) $to, (string) $from, null, true ) );

                foreach( $pairs as [$id, $text] ) {
                    $result[$id] = $map[$text];
                }

                yield (string) $to => $result;
            }
        }
    }


    /**
     * Returns the unique texts of the file ID and text pairs.
     *
     * @param list<array{0: string, 1: string}> $pairs File ID and text pairs
     * @return array<int, string> Unique texts
     */
    private static function unique( array $pairs ) : array
    {
        return array_values( array_unique( array_column( $pairs, 1 ) ) );
    }
}
