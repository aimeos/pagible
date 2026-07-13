<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Models\Page;
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
     * Canonicalizes JSON values while retaining list order and scalar types.
     *
     * @return array<mixed>
     */
    private function canon( mixed $value ) : array
    {
        if( is_object( $value ) || ( is_array( $value ) && !array_is_list( $value ) ) ) {
            $items = (array) $value;
            ksort( $items );

            foreach( $items as $key => $item ) {
                $items[$key] = $this->canon( $item );
            }

            return ['object', $items];
        }

        if( is_array( $value ) ) {
            return ['list', array_map( fn( $item ) => $this->canon( $item ), $value )];
        }

        return ['value', $value];
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


    /**
     * Recursively collects file references from structured entry data.
     *
     * @param mixed $value Entry data
     * @return array<int, string> Referenced file IDs
     */
    private function files( mixed $value ) : array
    {
        $ids = [];
        $this->refs( $value, $ids );

        return array_values( $ids );
    }


    private function pages( Connection $db ) : void
    {
        $db->table( 'cms_pages' )
            ->select( 'id', 'meta', 'config' )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                $updates = [];

                foreach( $rows as $row )
                {
                    $oldMeta = $this->decode( $row->meta );
                    $oldConfig = $this->decode( $row->config );
                    $meta = $this->structured( $oldMeta );
                    $config = $this->structured( $oldConfig );
                    $values = [];

                    if( !$this->same( $meta, $row->meta ) ) {
                        $values['meta'] = $this->encode( $meta );
                    }

                    if( !$this->same( $config, $row->config ) ) {
                        $values['config'] = $this->encode( $config );
                    }

                    if( $values !== [] ) {
                        $updates[$row->id] = $values;
                    }
                }

                if( $updates !== [] ) {
                    $db->transaction( function() use ( $db, $updates ) {
                        foreach( $updates as $id => $values ) {
                            $db->table( 'cms_pages' )->where( 'id', $id )->update( $values );
                        }
                    } );
                }
            } );
    }


    /**
     * Recursively collects file IDs without repeatedly copying intermediate lists.
     *
     * @param array<string, string> &$ids Referenced file IDs keyed by ID
     */
    private function refs( mixed $value, array &$ids ) : void
    {
        if( is_object( $value ) ) {
            $value = (array) $value;
        }

        if( !is_array( $value ) ) {
            return;
        }

        if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null ) ) {
            $ids[$value['id']] = $value['id'];
            return;
        }

        foreach( $value as $item ) {
            $this->refs( $item, $ids );
        }
    }


    /**
     * Compares generated data with stored JSON independent of object key order.
     */
    private function same( mixed $data, mixed $json ) : bool
    {
        if( is_string( $json ) ) {
            $json = json_decode( $json );
        }

        return $this->canon( $data ) === $this->canon( $json );
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
            ->where( 'versionable_type', Page::class )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                $updates = [];

                foreach( $rows as $row )
                {
                    $aux = $this->decode( $row->aux );

                    if( !is_array( $aux ) ) {
                        continue;
                    }

                    if( array_key_exists( 'meta', $aux ) ) {
                        $aux['meta'] = $this->structured( $aux['meta'] );
                    }

                    if( array_key_exists( 'config', $aux ) ) {
                        $aux['config'] = $this->structured( $aux['config'] );
                    }

                    if( !$this->same( $aux, $row->aux ) ) {
                        $updates[$row->id] = $this->encode( $aux );
                    }
                }

                if( $updates !== [] ) {
                    $db->transaction( function() use ( $db, $updates ) {
                        foreach( $updates as $id => $aux ) {
                            $db->table( 'cms_versions' )->where( 'id', $id )->update( ['aux' => $aux] );
                        }
                    } );
                }
            } );
    }
};
