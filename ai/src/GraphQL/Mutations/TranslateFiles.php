<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class TranslateFiles
{
    use ValidatesInputs;


    /**
     * Translates the descriptions of the files into the languages they are missing in and saves them as file drafts.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, File> Files with new drafts
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $langs = array_values( array_unique( array_map( 'strval', $args['lang'] ) ) );
        $files = File::with( 'latest' )->whereIn( 'id', array_unique( $args['id'] ) )->get();
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

        $changed = [];

        foreach( $todo as $to => $sources )
        {
            foreach( $sources as $from => $texts )
            {
                $result = $this->ai( fn() => Ai::translate( array_values( $texts ), $to, $from ) );

                foreach( array_keys( $texts ) as $idx => $id )
                {
                    if( isset( $result[$idx] ) ) {
                        $descs[$id][$to] = $result[$idx];
                        $changed[$id] = true;
                    }
                }
            }
        }

        $saved = [];

        foreach( $files as $file )
        {
            $id = (string) $file->id;

            if( isset( $changed[$id] ) ) {
                $saved[] = Resource::saveFile( $id, ['description' => $descs[$id]], Auth::user(), $file->latest_id );
            }
        }

        return $saved;
    }
}
