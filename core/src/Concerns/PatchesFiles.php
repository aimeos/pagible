<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Closure;


/**
 * Helpers for install commands updating application files.
 */
trait PatchesFiles
{
    /**
     * Appends the text to the file unless it already contains it.
     *
     * @param string $filename File path relative to the application base path
     * @param string $text Text to append
     * @return int 0 on success, 1 on failure
     */
    protected function append( string $filename, string $text ) : int
    {
        return $this->patch( $filename, function( string $content ) use ( $text ) {
            return str_contains( $content, $text ) ? $content : $content . "\n\n" . $text;
        } );
    }


    /**
     * Inserts the text after the search string unless the file already contains the marker.
     *
     * @param string $filename File path relative to the application base path
     * @param string $search String the text is inserted after
     * @param string $text Text to insert
     * @param string $marker String indicating that the file is already up to date
     * @param string $message Message printed if the text has been inserted, "%1$s" is replaced by the file name
     * @param bool $last TRUE to insert after the last occurrence of the search string instead of the first one
     * @return int 0 on success, 1 on failure
     */
    protected function insert( string $filename, string $search, string $text, string $marker, string $message, bool $last = false ) : int
    {
        return $this->patch( $filename, function( string $content ) use ( $filename, $search, $text, $marker, $message, $last ) {

            if( str_contains( $content, $marker ) ) {
                return $content;
            }

            if( ( $pos = $last ? strrpos( $content, $search ) : strpos( $content, $search ) ) === false ) {
                return $content;
            }

            $this->line( sprintf( $message, $filename ) );
            return substr_replace( $content, $text, $pos + strlen( $search ), 0 );
        }, null );
    }


    /**
     * Updates the file content using the given closure.
     *
     * @param string $filename File path relative to the application base path
     * @param Closure(string): ?string $fn Receives the file content and returns the updated content or NULL on failure
     * @param string|null $message Message printed if the file has been updated or NULL for none, "%1$s" is replaced by the file name
     * @return int 0 on success, 1 on failure
     */
    protected function patch( string $filename, Closure $fn, ?string $message = '  File [%1$s] updated' . PHP_EOL ) : int
    {
        $path = base_path( $filename );
        $content = file_get_contents( $path );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        if( ( $updated = $fn( $content ) ) === null ) {
            return 1;
        }

        if( $updated === $content ) {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        } elseif( file_put_contents( $path, $updated ) === false ) {
            $this->error( "  Updating file [$filename] failed!" );
            return 1;
        } elseif( $message !== null ) {
            $this->line( sprintf( $message, $filename ) );
        }

        return 0;
    }
}
