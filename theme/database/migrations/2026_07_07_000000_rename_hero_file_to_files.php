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
        $this->elements( $db );
        $this->versionData( $db );
        $this->versionAux( $db );
    }


    private function elements( Connection $db ) : void
    {
        $db->table( 'cms_elements' )
            ->select( 'id', 'data' )
            ->where( 'type', 'hero' )
            ->whereJsonContainsKey( 'data->file->id' )
            ->where( 'data->file->id', '!=', '' )
            ->where( 'data->file->type', 'file' )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $data = $this->decode( $row->data );

                    if( is_array( $data ) && $this->hero( $data ) ) {
                        $db->table( 'cms_elements' )->where( 'id', $row->id )->update( ['data' => $this->encode( $data )] );
                    }
                }
            } );
    }


    /**
     * @param array<int, mixed> $content
     */
    private function content( array &$content ) : bool
    {
        $changed = false;

        foreach( $content as &$item )
        {
            if( !is_array( $item ) || ( $item['type'] ?? null ) !== 'hero' || !isset( $item['data'] ) || !is_array( $item['data'] ) ) {
                continue;
            }

            $changed = $this->hero( $item['data'] ) || $changed;
        }

        return $changed;
    }


    private function decode( mixed $json ) : mixed
    {
        if( is_string( $json ) ) {
            return json_decode( $json, true );
        }

        if( is_array( $json ) ) {
            return $json;
        }

        if( is_object( $json ) ) {
            return json_decode( (string) json_encode( $json ), true );
        }

        return null;
    }


    private function encode( mixed $data ) : string
    {
        return (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }


    /**
     * @param array<string, mixed> $data
     */
    private function hero( array &$data ) : bool
    {
        if( !isset( $data['file'] ) || !is_array( $data['file'] ) || array_is_list( $data['file'] ) ) {
            return false;
        }

        if( ( $data['file']['type'] ?? null ) !== 'file' || empty( $data['file']['id'] ) ) {
            return false;
        }

        if( !isset( $data['files'] ) ) {
            $data['files'] = [$data['file']];
        }

        unset( $data['file'] );

        return true;
    }


    private function pages( Connection $db ) : void
    {
        $db->table( 'cms_pages' )
            ->select( 'id', 'content' )
            ->whereJsonLength( 'content', '>', 0 )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $content = $this->decode( $row->content );

                    if( is_array( $content ) && $this->content( $content ) ) {
                        $db->table( 'cms_pages' )->where( 'id', $row->id )->update( ['content' => $this->encode( $content )] );
                    }
                }
            } );
    }


    private function versionAux( Connection $db ) : void
    {
        $db->table( 'cms_versions' )
            ->select( 'id', 'aux' )
            ->whereJsonLength( 'aux->content', '>', 0 )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $aux = $this->decode( $row->aux );

                    if( is_array( $aux ) && isset( $aux['content'] ) && is_array( $aux['content'] ) && $this->content( $aux['content'] ) ) {
                        $db->table( 'cms_versions' )->where( 'id', $row->id )->update( ['aux' => $this->encode( $aux )] );
                    }
                }
            } );
    }


    private function versionData( Connection $db ) : void
    {
        $db->table( 'cms_versions' )
            ->select( 'id', 'data' )
            ->where( 'data->type', 'hero' )
            ->whereJsonContainsKey( 'data->data->file->id' )
            ->where( 'data->data->file->id', '!=', '' )
            ->where( 'data->data->file->type', 'file' )
            ->orderBy( 'id' )
            ->chunkById( 500, function( $rows ) use ( $db ) {
                foreach( $rows as $row )
                {
                    $data = $this->decode( $row->data );

                    if( is_array( $data ) && ( $data['type'] ?? null ) === 'hero' && isset( $data['data'] ) && is_array( $data['data'] ) && $this->hero( $data['data'] ) ) {
                        $db->table( 'cms_versions' )->where( 'id', $row->id )->update( ['data' => $this->encode( $data )] );
                    }
                }
            } );
    }
};
