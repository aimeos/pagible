<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Cms\Watch as CmsWatch;
use Aimeos\Prisma\Contracts\Provider;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Schema\Schema;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Values\Observation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


/**
 * AI operations shared by the GraphQL mutations and the MCP tools.
 *
 * Each operation uses the "cms.ai.<op>" provider, configuration and model
 * settings and records the provider call as Generated event for the editor.
 * The editor is resolved from the authenticated user.
 */
class Ai
{
    /**
     * Ensures that the JSON encoded input doesn't exceed the "cms.ai.maxinput" size and "cms.ai.maxdepth" nesting depth.
     *
     * @param mixed $value Input sent to the AI provider
     * @param string $name Name of the input used in the error message
     * @throws Exception If the input can't be encoded, is too large or too deeply nested
     */
    public static function checkInput( mixed $value, string $name = 'Content' ) : void
    {
        $max = max( 1, (int) config( 'cms.ai.maxinput', 1024 * 1024 ) );
        $depth = max( 1, (int) config( 'cms.ai.maxdepth', 20 ) );
        $json = json_encode( $value, 0, $depth );

        if( $json === false && json_last_error() === JSON_ERROR_DEPTH ) {
            throw new Exception( sprintf( '%s exceeds the maximum nesting depth of %d', $name, $depth ) );
        }

        if( $json === false || strlen( $json ) > $max ) {
            throw new Exception( sprintf( '%s exceeds the maximum input size of %d bytes', $name, $max ) );
        }
    }


    /**
     * Describes an image, audio or video file.
     *
     * @param File|string $file File model with at least disk, path and mime loaded or the file UUID
     * @param string|null $lang ISO language code of the description
     * @return string Description of the file
     * @throws Exception If the file type isn't supported
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no file with the UUID exists
     */
    public static function describe( File|string $file, ?string $lang = null ) : string
    {
        if( is_string( $file ) ) {
            $file = File::select( 'id', 'disk', 'path', 'mime' )->findOrFail( $file );
        }

        $type = explode( '/', (string) $file->mime, 2 )[0];
        $class = '\\Aimeos\\Prisma\\Files\\' . ucfirst( $type );

        if( !class_exists( $class ) ) {
            throw new Exception( sprintf( 'Unsupported file type "%s"', $file->mime ) );
        }

        return self::provider( $type, 'describe' )
            ->describe( self::media( $file, $class ), $lang, config( 'cms.ai.describe', [] ) ) // @phpstan-ignore-line method.notFound
            ->text();
    }


    /**
     * Removes the part of an image covered by the mask.
     *
     * @param Image $image Image to edit
     * @param Image $mask Mask image (black: keep, white: erase)
     * @return string Base64 encoded edited image
     */
    public static function erase( Image $image, Image $mask ) : string
    {
        return (string) self::provider( 'image', 'erase' )
            ->erase( $image, $mask, config( 'cms.ai.erase', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Loads stored files as Prisma file objects.
     *
     * Files of other media types are skipped if a specific Prisma class like
     * Image::class is passed.
     *
     * @template T of \Aimeos\Prisma\Files\File
     * @param array<int, string> $ids UUIDs of the files
     * @param class-string<T> $class Prisma file class, e.g. Image::class
     * @return array<int, T> List of Prisma file objects
     * @throws Exception If the user isn't allowed to view files
     */
    public static function files( array $ids, string $class = \Aimeos\Prisma\Files\File::class ) : array
    {
        if( empty( $ids ) ) {
            return [];
        }

        if( !Permission::can( 'file:view', Auth::user() ) ) {
            throw new Exception( 'Insufficient permissions' );
        }

        $type = $class !== \Aimeos\Prisma\Files\File::class ? strtolower( class_basename( $class ) ) . '/' : '';

        return File::whereIn( 'id', $ids )->select( 'id', 'tenant_id', 'disk', 'path', 'mime' )->get()
            ->filter( fn( File $file ) => str_starts_with( (string) $file->mime, $type ) )
            ->map( fn( File $file ) => self::media( $file, $class ) )
            ->values()->all();
    }


    /**
     * Generates a new image from a prompt.
     *
     * @param string $prompt Description of the image
     * @param array<int, Image> $images Reference images
     * @param string|null $context Additional context like style, mood or brand guidelines
     * @return string Base64 encoded image
     */
    public static function imagine( string $prompt, array $images = [], ?string $context = null ) : string
    {
        $config = config( 'cms.ai.imagine', [] ) + ['size' => ['1536x1024', '1792x1024', '1024x1024']];
        $prompt .= $context ? "\n\n" . $context : '';

        return (string) self::provider( 'image', 'imagine' )
            ->imagine( $prompt, $images, $config ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Replaces the masked area of an image based on a prompt.
     *
     * @param Image $image Image to edit
     * @param Image $mask Mask image marking the area to replace
     * @param string $prompt Description of the new content
     * @return string Base64 encoded edited image
     */
    public static function inpaint( Image $image, Image $mask, string $prompt ) : string
    {
        return (string) self::provider( 'image', 'inpaint' )
            ->inpaint( $image, $mask, $prompt, config( 'cms.ai.inpaint', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Removes the background of an image.
     *
     * @param Image $image Image to edit
     * @return string Base64 encoded edited image
     */
    public static function isolate( Image $image ) : string
    {
        return (string) self::provider( 'image', 'isolate' )
            ->isolate( $image, config( 'cms.ai.isolate', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Returns the observer that records AI provider calls as Generated events.
     *
     * The editor and tenant are captured now (editor resolved from the authenticated user when
     * null) so they are correct when the callback fires after the provider call completes.
     *
     * @param string|null $editor Editor identifier; resolved from the authenticated user when null
     * @param string|null $mutation Operation key override
     * @return \Closure(Observation): void Observer for Prisma::...->observe()
     */
    public static function observer( ?string $editor = null, ?string $mutation = null ) : \Closure
    {
        $editor ??= Utils::editor( Auth::user() );
        $tenant = Tenancy::value();

        return function( Observation $observation ) use ( $editor, $tenant, $mutation ) {
            CmsWatch::dispatch( Generated::class, fn() => new Generated(
                mutation: $mutation ?? $observation->operation,
                provider: $observation->provider,
                model: $observation->model ?? '',
                durationMs: $observation->durationMs,
                editor: $editor,
                tenant: $tenant,
                success: $observation->error === null,
                error: $observation->error?->getMessage(),
                inputTokens: $observation->usage?->promptTokens(),
                outputTokens: $observation->usage?->completionTokens(),
            ) );
        };
    }


    /**
     * Refines page content or structured entries based on a prompt.
     *
     * @param string $prompt Description of the changes
     * @param array<mixed> $content Content elements or structured entries to refine
     * @param string $type Schema section, e.g. "content" or "meta"
     * @param string|null $pagetype Page type to limit the content element types
     * @param string|null $context Additional context like audience or tone
     * @param string|null $lang ISO language code of the refined content
     * @return array<mixed> Refined content
     * @throws Exception If the AI response is invalid
     */
    public static function refine( string $prompt, array $content, string $type = 'content', ?string $pagetype = null,
        ?string $context = null, ?string $lang = null ) : array
    {
        $schema = Schema::fromArray( 'response', JsonSchema::build( $type, $pagetype ) );
        $system = view( 'cms::prompts.refine' )->render() . "\n" . $context . ( $lang ? "\nWrite the content in language: " . $lang : '' );

        $structured = self::lifted( fn() => self::provider( 'text', 'refine', 'structure' )
            ->withClientOptions( ['timeout' => (int) config( 'cms.ai.timeout' ), 'connect_timeout' => 10] )
            ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
            ->withSystemPrompt( $system )
            // read-only tools: stored content in the prompt must not be able to change or leak data
            ->withTools( [
                Tools::laravel( CmsTools\GetPage::class ),
                Tools::laravel( CmsTools\GetPageHistory::class ),
                Tools::laravel( CmsTools\GetPageMetrics::class ),
                Tools::laravel( CmsTools\GetPageTree::class ),
                Tools::laravel( CmsTools\SearchPages::class ),
                Tools::laravel( CmsTools\GetElement::class ),
                Tools::laravel( CmsTools\GetFile::class )
            ] )
            ->structure( $prompt . "\n\nContent as JSON:\n" . json_encode( $content ), $schema ) // @phpstan-ignore-line method.notFound
            ->structured() );

        if( !$structured ) {
            throw new Exception( 'No structured content returned in refine response' );
        }

        if( $errors = $schema->validate( $structured ) ) {
            Log::warning( 'Invalid refine response', ['errors' => $errors] );
            throw new Exception( config( 'app.debug' ) ? 'Invalid refine response: ' . implode( '; ', $errors ) : 'Invalid content in refine response' );
        }

        if( $type === 'content' ) {
            return Refiner::merge( $content, $structured['contents'] ?? [], $pagetype );
        }

        foreach( $structured as $key => $data )
        {
            if( is_array( $data ) ) {
                $content[$key] = Validation::entry( $key, array_filter( $data, fn( $v ) => $v !== null ), $type );
            }
        }

        return (array) Validation::structured( $content, $type );
    }


    /**
     * Edits an image based on a prompt.
     *
     * @param Image $image Image to edit
     * @param string $prompt Description of the changes
     * @return string Base64 encoded edited image
     */
    public static function repaint( Image $image, string $prompt ) : string
    {
        return (string) self::provider( 'image', 'repaint' )
            ->repaint( $image, $prompt, config( 'cms.ai.repaint', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Transcribes the speech in an audio file.
     *
     * @param Audio $audio Audio file
     * @return array<int, array{start: string, end: string, text: string}> Transcription segments
     */
    public static function transcribe( Audio $audio ) : array
    {
        $data = self::provider( 'audio', 'transcribe' )
            ->transcribe( $audio, null, config( 'cms.ai.transcribe', [] ) ) // @phpstan-ignore-line method.notFound
            ->structured();

        return array_map( fn( $entry ) => [
            'start' => Utils::formatSeconds( $entry['start'] ),
            'end' => Utils::formatSeconds( $entry['end'] ),
            'text' => $entry['text'],
        ], $data );
    }


    /**
     * Translates texts into another language.
     *
     * @param array<int, string> $texts Texts to translate
     * @param string $to Target language code
     * @param string|null $from Source language code, auto-detected if NULL
     * @param string|null $context Additional context like the topic of the texts
     * @return array<int, string> Translated texts in the same order
     */
    public static function translate( array $texts, string $to, ?string $from = null, ?string $context = null ) : array
    {
        $config = config( 'cms.ai.translate', [] ) + [
            'ignore_tags' => ['x'],
            'tag_handling' => 'xml',
            'preserve_formatting' => true,
            'model_type' => 'prefer_quality_optimized',
        ];

        return self::provider( 'text', 'translate', null, $config )
            ->translate( $texts, $to, $from, $context, $config ) // @phpstan-ignore-line method.notFound
            ->texts();
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
     */
    public static function uncrop( Image $image, int $top, int $right, int $bottom, int $left ) : string
    {
        return (string) self::provider( 'image', 'uncrop' )
            ->uncrop( $image, $top, $right, $bottom, $left, config( 'cms.ai.uncrop', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Increases the resolution of an image.
     *
     * @param Image $image Image to upscale
     * @param int $factor Upscale factor
     * @return string Base64 encoded edited image
     */
    public static function upscale( Image $image, int $factor ) : string
    {
        return (string) self::provider( 'image', 'upscale' )
            ->upscale( $image, $factor, config( 'cms.ai.upscale', [] ) ) // @phpstan-ignore-line method.notFound
            ->base64();
    }


    /**
     * Writes a text based on a prompt and optional files.
     *
     * @param string $prompt Description of the text
     * @param array<int, \Aimeos\Prisma\Files\File> $files Files used as source
     * @param string|null $context Additional context like audience or tone
     * @return string Generated text
     */
    public static function write( string $prompt, array $files = [], ?string $context = null ) : string
    {
        $system = view( 'cms::prompts.write' )->render() . "\n" . $context;

        return self::lifted( fn() => self::provider( 'text', 'write' )
            ->withClientOptions( ['timeout' => (int) config( 'cms.ai.timeout' ), 'connect_timeout' => 10] )
            ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
            ->withSystemPrompt( $system )
            ->withTools( [Tools::provider( 'web_search' ), Tools::provider( 'web_fetch' )] )
            ->write( $prompt, $files, config( 'cms.ai.write', [] ) ) // @phpstan-ignore-line method.notFound
            ->text() );
    }


    /**
     * Calls the AI provider without PHP's default execution time limit.
     *
     * @param \Closure $fn Function calling the provider
     * @return mixed Return value of the function
     */
    protected static function lifted( \Closure $fn ) : mixed
    {
        $limit = (int) ini_get( 'max_execution_time' );
        set_time_limit( (int) config( 'cms.ai.timeout' ) );

        try {
            return $fn();
        } finally {
            set_time_limit( $limit );
        }
    }


    /**
     * Builds a Prisma file object from a stored file model.
     *
     * @template T of \Aimeos\Prisma\Files\File
     * @param File $file File model with at least disk, path and mime loaded
     * @param class-string<T> $class Prisma file class, e.g. Image::class
     * @return T Prisma file object
     */
    protected static function media( File $file, string $class ) : \Aimeos\Prisma\Files\File
    {
        if( str_starts_with( (string) $file->path, 'http' ) ) {
            return $class::fromUrl( (string) $file->path, $file->mime, !(bool) config( 'cms.allow-internal' ) );
        }

        return $class::fromStoragePath( (string) $file->path, File::diskName( (string) $file->disk ), $file->mime );
    }


    /**
     * Returns the configured Prisma provider for the given AI operation.
     *
     * @param string $type Media type, e.g. "text", "image" or "audio"
     * @param string $op Operation name used as "cms.ai.<op>" configuration key
     * @param string|null $method Provider method if it differs from the operation name
     * @param array<string, mixed>|null $config Provider configuration, "cms.ai.<op>" if NULL
     * @return Provider Provider instance supporting the operation
     * @throws \Aimeos\Prisma\Exceptions\PrismaException If no provider is configured or the operation isn't supported
     */
    protected static function provider( string $type, string $op, ?string $method = null, ?array $config = null ) : Provider
    {
        return Prisma::type( $type )->observe( self::observer() )
            ->using( config( "cms.ai.$op.provider" ), $config ?? config( "cms.ai.$op", [] ) )
            ->model( config( "cms.ai.$op.model" ) )
            ->ensure( $method ?? $op );
    }
}
