<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;


class Utils
{
    public static function files( Page $page ) : Collection
    {
        $lang = $page->lang;

        return Collection::make( $page->content )
            ->map( fn( $item ) => $item->files ?? [] )
            ->collapse()
            ->unique()
            ->map( fn( $id ) => $page->files[$id] ?? null )
            ->filter()
            ->pluck( null, 'id' )
            ->each( fn( $file ) => $file->description = $file->description?->{$lang}
                ?? $file->description?->{substr( $lang, 0, 2 )}
                ?? null
        );
    }


    public static function mimetype( string $path ) : string
    {
        if( str_starts_with( $path, 'http') )
        {
            if( !filter_var( $path, FILTER_VALIDATE_URL ) ) {
                throw new \RuntimeException( 'Invalid URL' );
            }

            $response = Http::withHeaders( ['Range' => 'bytes=0-299'] )->get( $path );

            if( !$response->successful() ) {
                throw new \RuntimeException( 'URL not accessible' );
            }

            $buffer = $response->body();
        }
        else
        {
            $stream = Storage::disk( config( 'cms.storage.disk', 'public' ) )->readStream( $path );

            if( !$stream ) {
                throw new \RuntimeException( 'File not accessible' );
            }

            if( ( $buffer = fread( $stream, 300 ) ) === false ) {
                fclose($stream);
                throw new \RuntimeException( 'File not readable' );

            }

            fclose($stream);
        }

        $finfo = new \finfo( FILEINFO_MIME_TYPE );

        if( ( $mime = $finfo->buffer( $buffer ) ) === false ) {
            throw new \RuntimeException( 'Failed to get mime type' );
        }

        return $mime;
    }
}