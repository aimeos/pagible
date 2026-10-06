<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\File;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;


/**
 * File helpers shared by the importers.
 */
class Files
{
    /**
     * Creates a File record with a published version.
     *
     * @param string $mime MIME type of the file
     * @param string $name Descriptive file name
     * @param string $path Remote URL or path of the file
     * @param string $editor Editor name for the file and its version
     * @param bool $download TRUE to download and store the file, FALSE to reference the path
     * @return File Published file
     */
    public static function create( string $mime, string $name, string $path, string $editor, bool $download ) : File
    {
        $file = new File();
        $resource = null;

        try
        {
            $file->mime = $mime;
            $file->name = $name;
            $file->editor = $editor;

            if( $download )
            {
                $resource = $file->fetchUrl( $path ) ?? throw new Exception( 'Unable to create temporary file' );

                if( $mime === 'image/svg+xml' ) {
                    self::svg( $resource );
                }

                $tmp = stream_get_meta_data( $resource )['uri'] ?? null;
                $filename = basename( rawurldecode( (string) parse_url( $path, PHP_URL_PATH ) ) ) ?: $name;

                if( !is_string( $tmp ) ) {
                    throw new Exception( 'Unable to create temporary file' );
                }

                $file->ingest( new UploadedFile( $tmp, $filename, $mime, null, true ) );
            }
            else
            {
                $file->path = $path;
                $file->previews = [];
            }

            $file->save();

            $snapshot = File::snapshot( $file->toArray() );
            $version = $file->versions()->forceCreate( [
                'lang' => $file->lang,
                'data' => $snapshot['data'],
                'aux' => $snapshot['aux'],
                'editor' => $editor,
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );

            return $file;
        }
        catch( \Throwable $e )
        {
            $file->removePreviews()->removeFile();
            throw $e;
        }
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Guesses the MIME type from the file extension of a path or URL.
     *
     * @param string $path File path or URL
     * @return string MIME type, media types are preferred if the extension is ambiguous
     */
    public static function mime( string $path ) : string
    {
        $ext = strtolower( pathinfo( parse_url( $path, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
        $types = $ext !== '' ? MimeTypes::getDefault()->getMimeTypes( $ext ) : [];

        return current( preg_grep( '#^(audio|image|video)/#', $types ) ?: $types ) ?: 'application/octet-stream';
    }


    /**
     * Adds the XML declaration needed by fileinfo to recognize plain SVG markup.
     *
     * Also replaces the undeclared Adobe Illustrator namespace entities.
     *
     * @param resource $resource SVG file stream
     */
    protected static function svg( $resource ) : void
    {
        rewind( $resource );
        $content = stream_get_contents( $resource );

        if( !is_string( $content ) ) {
            throw new Exception( 'Unable to read SVG file' );
        }

        $normalized = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $content );
        $normalized = strtr( $normalized, [
            '&ns_extend;' => 'http://ns.adobe.com/Extensibility/1.0/',
            '&ns_ai;' => 'http://ns.adobe.com/AdobeIllustrator/10.0/',
            '&ns_graphs;' => 'http://ns.adobe.com/Graphs/1.0/',
            '&ns_vars;' => 'http://ns.adobe.com/Variables/1.0/',
            '&ns_imrep;' => 'http://ns.adobe.com/ImageReplacement/1.0/',
            '&ns_sfw;' => 'http://ns.adobe.com/SaveForWeb/1.0/',
            '&ns_custom;' => 'http://ns.adobe.com/GenericCustomNamespace/1.0/',
            '&ns_adobe_xpath;' => 'http://ns.adobe.com/AdobeXPath/1.0/',
        ] );

        if( preg_match( '/^\s*<\?xml\b/i', $normalized ) !== 1 ) {
            $normalized = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $normalized;
        }

        if( $normalized !== $content )
        {
            rewind( $resource );

            if( !ftruncate( $resource, 0 ) || fwrite( $resource, $normalized ) !== strlen( $normalized ) ) {
                throw new Exception( 'Unable to normalize SVG file' );
            }
        }

        rewind( $resource );
    }
}
