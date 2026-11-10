<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Prisma\Contracts\Provider;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Files\File as PrismaFile;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


/**
 * AI operations shared by the GraphQL mutations and the MCP tools.
 */
final class Ai
{
    /**
     * Preferred sizes of generated images, landscape first.
     */
    private const SIZES = ['1536x1024', '1792x1024', '1024x1024'];


    /**
     * Describes an image, audio or video file.
     *
     * @param PrismaFile $media Audio, image or video file, e.g. by Media::load() or Media::media()
     * @param string|null $lang ISO language code of the description
     * @return string Description of the file
     * @throws Exception If the file type isn't supported
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function describe( PrismaFile $media, ?string $lang = null ) : string
    {
        $type = Media::type( $media );

        if( !$type ) {
            throw new Exception( __( 'Unsupported file type ":mime"', ['mime' => $media->mimeType()] ) );
        }

        return AiClient::call( $type, AiClient::DESCRIBE, fn( Provider $p, array $o ) => $p->describe( $media, $lang, $o ) ) // @phpstan-ignore-line method.notFound
            ->text() ?? throw new Exception( __( 'No description returned' ) );
    }


    /**
     * Removes the part of an image covered by the mask.
     *
     * @param Image $image Image to edit
     * @param Image $mask Mask image (black: keep, white: erase)
     * @return string Base64 encoded edited image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function erase( Image $image, Image $mask ) : string
    {
        return self::image( 'erase', fn( Provider $p, array $o ) => $p->erase( $image, $mask, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Generates a new image from a prompt.
     *
     * @param string $prompt Description of the image
     * @param array<int, Image> $images Reference images
     * @param string|null $context Additional context like style, mood or brand guidelines
     * @return string Base64 encoded image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function imagine( string $prompt, array $images = [], ?string $context = null ) : string
    {
        // image providers have no system prompt, so the context is part of the prompt
        $prompt .= $context ? "\n\n" . $context : '';

        // published configs of older versions have no sizes
        $fn = fn( Provider $p, array $o ) => $p->imagine( $prompt, $images, $o + ['size' => self::SIZES] ); // @phpstan-ignore-line method.notFound
        return self::image( 'imagine', $fn );
    }


    /**
     * Replaces the masked area of an image based on a prompt.
     *
     * @param Image $image Image to edit
     * @param Image $mask Mask image marking the area to replace
     * @param string $prompt Description of the new content
     * @return string Base64 encoded edited image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function inpaint( Image $image, Image $mask, string $prompt ) : string
    {
        return self::image( 'inpaint', fn( Provider $p, array $o ) => $p->inpaint( $image, $mask, $prompt, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Removes the background of an image.
     *
     * @param Image $image Image to edit
     * @return string Base64 encoded edited image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function isolate( Image $image ) : string
    {
        return self::image( 'isolate', fn( Provider $p, array $o ) => $p->isolate( $image, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Summarizes a page for its meta description.
     *
     * The content may contain instructions of other editors, so no web pages are fetched.
     *
     * @param string $title Page title
     * @param string $content Text content of the page
     * @param string|null $lang ISO language code of the summary
     * @param int|null $max Maximum number of characters or NULL for no limit
     * @return string Summary without enclosing quotes
     * @throws Exception If the content is too large or no text is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function meta( string $title, string $content, ?string $lang = null, ?int $max = null ) : string
    {
        AiClient::checkInput( $content, __( 'Page content' ) );

        $system = view( 'cms::prompts.description', ['lang' => $lang, 'max' => $max] )->render();
        return Text::shorten( self::generate( $system, "Page title: {$title}\n\nPage content:\n{$content}" ), $max );
    }


    /**
     * Edits an image based on a prompt.
     *
     * @param Image $image Image to edit
     * @param string $prompt Description of the changes
     * @return string Base64 encoded edited image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function repaint( Image $image, string $prompt ) : string
    {
        return self::image( 'repaint', fn( Provider $p, array $o ) => $p->repaint( $image, $prompt, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Transcribes the speech in an audio file.
     *
     * @param Audio $audio Audio file
     * @param string|null $lang ISO language code of the speech or NULL to detect it
     * @return array<int, array{start: string, end: string, text: string}> Transcription segments
     * @throws Exception If the transcription is invalid
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function transcribe( Audio $audio, ?string $lang = null ) : array
    {
        $data = AiClient::call( 'audio', 'transcribe', fn( Provider $p, array $o ) => $p->transcribe( $audio, $lang, $o ) ) // @phpstan-ignore-line method.notFound
            ->structured() ?: throw new Exception( __( 'No transcription returned' ) );

        return array_map( fn( $entry ) => self::segment( $entry ), $data );
    }


    /**
     * Extends an image outwards.
     *
     * @param Image $image Image to extend
     * @param int $top Pixels to add at the top
     * @param int $right Pixels to add on the right
     * @param int $bottom Pixels to add at the bottom
     * @param int $left Pixels to add on the left
     * @return string Base64 encoded edited image
     * @throws Exception If no side is extended or no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function uncrop( Image $image, int $top = 0, int $right = 0, int $bottom = 0, int $left = 0 ) : string
    {
        if( max( $top, $right, $bottom, $left ) < 1 ) {
            throw new Exception( __( 'At least one side of the image must be extended' ) );
        }

        self::pixels( $image, 1, $left + $right, $top + $bottom );

        return self::image( 'uncrop', fn( Provider $p, array $o ) => $p->uncrop( $image, $top, $right, $bottom, $left, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Increases the resolution of an image.
     *
     * @param Image $image Image to upscale
     * @param int $factor Upscale factor
     * @return string Base64 encoded edited image
     * @throws Exception If the result would be too large or no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function upscale( Image $image, int $factor ) : string
    {
        self::pixels( $image, $factor );

        return self::image( 'upscale', fn( Provider $p, array $o ) => $p->upscale( $image, $factor, $o ) ); // @phpstan-ignore-line method.notFound
    }


    /**
     * Writes a text based on a prompt and optional files.
     *
     * @param string $prompt Description of the text
     * @param array<int, PrismaFile> $files Files used as source
     * @param string|null $context Additional context like audience or tone
     * @return string Generated text
     * @throws Exception If no text is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function write( string $prompt, array $files = [], ?string $context = null ) : string
    {
        $system = AiClient::system( view( 'cms::prompts.write' )->render(), $context );

        // the context can contain the page content of other editors, so no web pages are fetched
        return self::generate( $system, $prompt, $files, ['web_search'] );
    }


    /**
     * Writes a text which may use the passed web tools of the provider.
     *
     * @param string $system System prompt
     * @param string $prompt Description of the text
     * @param array<int, PrismaFile> $files Files used as source
     * @param array<int, string> $web Provider web tools, e.g. "web_fetch" only if the input is trusted
     * @return string Generated text
     * @throws PrismaException If no provider is configured or writing isn't supported
     * @throws Exception If no text is returned
     */
    private static function generate( string $system, string $prompt, array $files = [], array $web = [] ) : string
    {
        return AiClient::lifted( fn() => AiClient::prompt( AiClient::WRITE, $system, $web )
            ->write( $prompt, $files, AiClient::options( AiClient::WRITE ) ) // @phpstan-ignore-line method.notFound
            ->text() ?? throw new Exception( __( 'No text returned' ) ) );
    }


    /**
     * Executes an image operation and returns the resulting image.
     *
     * @param string $op Operation name, e.g. "erase"
     * @param \Closure(Provider, array<string, mixed>): FileResponse $fn Receives the provider and the request options
     * @return string Base64 encoded image
     * @throws Exception If no image is returned
     * @throws PrismaException If no provider is configured or the request fails
     */
    private static function image( string $op, \Closure $fn ) : string
    {
        return AiClient::call( 'image', $op, $fn )->base64() ?? throw new Exception( __( 'No image returned' ) );
    }


    /**
     * Ensures that the edited image won't exceed "cms.upload.maxpixels" before the provider charges for it.
     *
     * @param Image $image Image to edit
     * @param int $factor Factor the width and height are multiplied with
     * @param int $width Pixels added to the width
     * @param int $height Pixels added to the height
     * @throws Exception If the image is invalid or the edited image would be too large
     */
    private static function pixels( Image $image, int $factor = 1, int $width = 0, int $height = 0 ) : void
    {
        $info = @getimagesizefromstring( (string) $image->binary() );

        if( !$info ) {
            throw new Exception( __( 'Invalid image' ) );
        }

        $max = max( 1, (int) config( 'cms.upload.maxpixels', 4096 * 4096 ) );

        if( ( $info[0] + $width ) * ( $info[1] + $height ) * $factor * $factor > $max ) {
            throw new Exception( __( 'The edited image would exceed the maximum size of :max pixels', ['max' => $max] ) );
        }
    }


    /**
     * Validates a transcription segment returned by the provider and formats its times.
     *
     * @param mixed $entry Segment with start and end in seconds and the text
     * @return array{start: string, end: string, text: string} Segment with formatted times
     * @throws Exception If the segment is invalid
     */
    private static function segment( mixed $entry ) : array
    {
        $valid = is_array( $entry )
            && is_numeric( $entry['start'] ?? null )
            && is_numeric( $entry['end'] ?? null )
            && is_string( $entry['text'] ?? null );

        if( !$valid ) {
            throw new Exception( __( 'Invalid transcription returned' ) );
        }

        return [
            'start' => Text::timestamp( (float) $entry['start'] ),
            'end' => Text::timestamp( (float) $entry['end'] ),
            'text' => $entry['text'],
        ];
    }
}
