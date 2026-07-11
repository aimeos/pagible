<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->pages( $db );
        $this->versions( $db );
    }


    /**
     * Recursively collects file references from structured entry data.
     *
     * @param mixed $value Entry data
     * @return array<int, string> Referenced file IDs
     */
    private function files( mixed $value ) : array
    {
        if( !is_array( $value ) ) {
            return [];
        }

        if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null ) ) {
            return [$value['id']];
        }

        $ids = [];

        foreach( $value as $item ) {
            $ids = array_merge( $ids, $this->files( $item ) );
        }

        return array_values( array_unique( $ids ) );
    }


    private function pages( Connection $db ) : void
    {
        $db->table( 'cms_pages' )
            ->select( 'id', 'meta', 'config' )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $oldMeta = $this->decode( $row->meta );
                    $oldConfig = $this->decode( $row->config );
                    $meta = $this->structured( $oldMeta );
                    $config = $this->structured( $oldConfig );

                    if( $meta !== $oldMeta || $config !== $oldConfig ) {
                        $db->table( 'cms_pages' )->where( 'id', $row->id )->update( [
                            'meta' => $this->encode( $meta ),
                            'config' => $this->encode( $config ),
                        ] );
                    }
                }
            } );
    }


    /**
     * Converts representations persisted by the 0.11 release to keyed canonical entries.
     *
     * @param mixed $items Meta/config data
     * @return array<mixed>|object Canonical entries keyed by type, or unsupported data unchanged
     */
    private function structured( mixed $items ) : array|object
    {
        if( !is_array( $items ) ) {
            return new \stdClass();
        }

        if( $items === [] ) {
            return new \stdClass();
        }

        if( isset( $items['type'] ) && array_key_exists( 'data', $items ) ) {
            $items = [(string) $items['type'] => $items];
        }

        // List-shaped meta/config was introduced by demo seeders after 0.11.
        if( array_is_list( $items ) ) {
            return $items;
        }

        $result = new \stdClass();

        foreach( $items as $key => $value )
        {
            if( !is_array( $value ) ) {
                $value = ['value' => $value];
            }

            $entry = array_key_exists( 'type', $value ) && array_key_exists( 'data', $value );
            $type = $entry ? (string) ( $value['type'] ?? '' ) : (string) $key;

            if( $type === '' ) {
                continue;
            }

            $data = $entry ? $value['data'] ?? [] : $value;

            if( !is_array( $data ) ) {
                $data = ['value' => $data];
            }

            $result->{$type} = [
                'type' => $type,
                'data' => $data,
                'files' => $this->files( $data ),
            ];
        }

        return $result;
    }


    private function versions( Connection $db ) : void
    {
        $db->table( 'cms_versions' )
            ->select( 'id', 'aux' )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $aux = $this->decode( $row->aux );

                    if( !is_array( $aux ) ) {
                        continue;
                    }

                    $original = $aux;

                    if( array_key_exists( 'meta', $aux ) ) {
                        $aux['meta'] = $this->structured( $aux['meta'] );
                    }

                    if( array_key_exists( 'config', $aux ) ) {
                        $aux['config'] = $this->structured( $aux['config'] );
                    }

                    if( $aux !== $original ) {
                        $db->table( 'cms_versions' )->where( 'id', $row->id )->update( ['aux' => $this->encode( $aux )] );
                    }
                }
            } );
    }


    private function decode( mixed $json ) : mixed
    {
        if( is_string( $json ) ) {
            return json_decode( $json, true );
        }

        if( is_object( $json ) ) {
            return json_decode( (string) json_encode( $json ), true );
        }

        return $json;
    }


    private function encode( mixed $data ) : string
    {
        return (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }
};
