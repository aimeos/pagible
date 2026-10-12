<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Jobs\Throttled;
use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Illuminate\Support\Facades\Auth;


final class TranslateFiles
{
    use ValidatesInputs;


    /**
     * Translates the descriptions of the files into the languages they are missing in and saves them as file drafts.
     *
     * The schema limits the request to 100 files and 10 languages and the provider calls
     * count against the "cms.ai.ratelimit" of the tenant like queued page translations.
     * All provider calls are reserved before the first one is sent, so throttled requests
     * fail without translating anything.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, File> Files with new drafts
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $ids = array_values( array_unique( array_map( 'strval', $args['id'] ) ) );
        $langs = array_values( array_unique( array_map( 'strval', $args['lang'] ) ) );
        $files = File::with( 'latest' )->whereIn( 'id', $ids )->get()->keyBy( 'id' );
        $descs = $todo = [];

        foreach( $files as $file )
        {
            $id = (string) $file->id;
            $desc = array_filter( (array) ( $file->latest?->aux->description ?? $file->description ), fn( $v ) => is_string( $v ) && trim( $v ) !== '' );

            if( empty( $desc ) ) {
                continue;
            }

            $from = isset( $desc[(string) $file->lang] ) ? (string) $file->lang
                : ( isset( $desc[app()->getLocale()] ) ? app()->getLocale() : (string) array_key_first( $desc ) );

            foreach( $langs as $lang )
            {
                if( !isset( $desc[$lang] ) ) {
                    $todo[$lang][$from][$id] = $desc[$from];
                }
            }

            $descs[$id] = $desc;
        }

        // identical descriptions are translated only once
        $uniques = [];
        $calls = 0;

        foreach( $todo as $to => $sources )
        {
            foreach( $sources as $from => $texts ) {
                $calls += count( Ai::chunks( $uniques[$to][$from] = array_values( array_unique( $texts ) ) ) );
            }
        }

        try
        {
            // reserve the provider calls of all languages before sending the first one
            if( $calls > 0 ) {
                TranslatePage::reserve( (string) Tenancy::value(), $calls );
            }
        }
        catch( Throttled $e )
        {
            throw new Exception( 'Too many translations, please try again later' );
        }

        $changed = [];

        foreach( $todo as $to => $sources )
        {
            foreach( $sources as $from => $texts )
            {
                $unique = $uniques[$to][$from];
                $result = $this->ai( fn() => Ai::translate( $unique, $to, $from ) );

                        foreach( $texts as $id => $text )
                {
                    $idx = array_search( $text, $unique, true );

                    if( $idx !== false && isset( $result[$idx] ) ) {
                        $descs[$id][$to] = $result[$idx];
                        $changed[$id] = $files->get( $id )?->latest_id;
                    }
                }
            }
        }

        $saved = [];

        foreach( $changed as $id => $latestId ) {
            $saved[] = Resource::saveFile( $id, ['description' => $descs[$id]], Auth::user(), $latestId );
        }

        return $saved;
    }
}
