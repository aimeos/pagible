<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;


class Utils
{
    private static int $counter = 0;


    /**
     * Returns a collection of files associated with the given page.
     *
     * @param Page $page The page object containing content and files
     * @return Collection A collection of File models associated with the page
     */
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


    /**
     * Determines the MIME type of a file located at the given path or URL.
     *
     * @param string $path The file path or URL
     * @return string The MIME type of the file
     * @throws \RuntimeException If the file cannot be accessed or read
     */
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


    /**
     * Generates a slug from the given title.
     *
     * @param string $title The title to generate a slug from
     * @return string The generated slug
     */
    public static function slugify( string $title ): string
    {
        $title = preg_replace( '/[?&=%#@!$^*()+=\[\]{}|\\"\'<>;:.,_\s]/u', '-', $title );
        $title = preg_replace( '/-+/', '-', $title );
        $title = preg_replace( '/^-|-$/', '', $title );

        return mb_strtolower( trim( $title, '-' ) );
    }


    /**
     * Generates a unique ID for the page content element.
     *
     * This ID is a 6-character string that starts with a letter (A-Z, a-z) and is followed by 5 alphanumeric characters.
     * The first character is chosen from the first 52 characters of the base64 encoding,
     * while the remaining characters can be any of the 64 base64 characters.
     * The ID is based on the current time in milliseconds since a fixed epoch (2025-01-01T00:00:00Z),
     * ensuring that IDs are unique and non-repeating for approximately 70 years.
     *
     * @return string A unique 6-character ID for the page content element
     */
    public static function uid(): string
    {
        $base64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $epoch = strtotime( '2025-01-01T00:00:00Z' ) * 1000;

        $value = ( ( (int) ( ( microtime( true ) * 1000 - $epoch ) / 4096 ) ) << 7 ) | self::$counter;
        self::$counter = ( self::$counter + 1 ) & 0b01111111;

        $id = '';
        for( $i = 0; $i < 6; $i++ )
        {
            // First character: only A-Z/a-z (index % 52), others: full 64-character set
            $index = ($value >> 6 * (5 - $i)) & 63;
            $id .= $base64[$i === 0 ? $index % 52 : $index];
        }

        return $id;
    }
}