<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Models\File;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move file descriptions and transcriptions into auxiliary version data.
     */
    public function up(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $db->table( 'cms_versions' )
            ->select( 'id', 'data', 'aux' )
            ->where( 'versionable_type', File::class )
            ->where( function( $query ) {
                $query->whereJsonContainsKey( 'data->description' )
                    ->orWhereJsonContainsKey( 'data->transcription' );
            } )
            ->orderBy( 'id' )
            ->chunkById( 100, function( $rows ) use ( $db ) {
                $updates = [];

                foreach( $rows as $row )
                {
                    $data = $this->decode( $row->data );
                    $aux = $this->decode( $row->aux );

                    foreach( ['description', 'transcription'] as $key )
                    {
                        if( array_key_exists( $key, $data ) ) {
                            $aux[$key] ??= $data[$key];
                            unset( $data[$key] );
                        }
                    }

                    $updates[$row->id] = [
                        'data' => $this->encode( $data ),
                        'aux' => $this->encode( $aux ),
                    ];
                }

                $db->transaction( function() use ( $db, $updates ) {
                    foreach( $updates as $id => $values ) {
                        $db->table( 'cms_versions' )->where( 'id', $id )->update( $values );
                    }
                } );
            }, 'id' );
    }


    /**
     * Decode a JSON value into an associative array.
     *
     * @return array<string, mixed>
     */
    private function decode( mixed $value ): array
    {
        if( is_string( $value ) ) {
            $value = json_decode( $value, true );
        }

        return is_array( $value ) ? $value : [];
    }


    /**
     * Encode a value for a JSON database column.
     */
    private function encode( mixed $value ): string
    {
        return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }
};
