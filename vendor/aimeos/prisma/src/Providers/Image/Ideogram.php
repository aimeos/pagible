<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Background;
use Aimeos\Prisma\Contracts\Image\Describe;
use Aimeos\Prisma\Contracts\Image\Inpaint;
use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Repaint;
use Aimeos\Prisma\Contracts\Image\Upscale;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Psr\Http\Message\ResponseInterface;


class Ideogram
    extends Base
    implements Background, Describe, Imagine, Inpaint, Repaint, Upscale
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'Api-Key', (string) $config['api_key'] );
        $this->baseUrl( 'https://api.ideogram.ai' );
    }


    public function background( Image $image, string $prompt, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'color_palette', 'magic_prompt', 'rendering_speed', 'seed', 'style_codes',
            'style_preset', 'style_reference_images'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );

        $files = $this->toFiles( $options, ['style_reference_images'] );

        $request = $this->request( ['prompt' => $prompt] + $allowed, ['image' => $image] + $files );
        $response = $this->client()->post( 'v1/ideogram-v3/replace-background', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function describe( Image $image, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $allowed = $this->allowed( $options, ['describe_model_version'] );

        $request = $this->request( $allowed, ['image_file' => $image] );
        $response = $this->client()->post( 'describe', ['multipart' => $request] );

        $this->validate( $response );

        $result = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $item = current( $result['descriptions'] ?? [] ) ?: null;

        return TextResponse::fromText( $item['text'] ?? null );
    }


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'aspect_ratio', 'character_reference_images', 'character_reference_images_mask', 'color_palette',
            'magic_prompt', 'negative_prompt', 'rendering_speed', 'resolution', 'seed', 'style_codes',
            'style_preset', 'style_type'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );
        $files = $this->toFiles( $options, ['character_reference_images', 'character_reference_images_mask'] );

        $request = $this->request( ['prompt' => $prompt] + $allowed, ['style_reference_images' => $images] + $files );
        $response = $this->client()->post( 'v1/ideogram-v3/generate', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'character_reference_images', 'character_reference_images_mask', 'color_palette',
            'magic_prompt', 'rendering_speed', 'seed', 'style_codes', 'style_preset',
            'style_reference_images', 'style_type'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );
        $files = $this->toFiles( $options, ['character_reference_images', 'character_reference_images_mask', 'style_reference_images'] );

        $request = $this->request( ['prompt' => $prompt] + $allowed, ['image' => $image, 'mask' => $mask] + $files );
        $response = $this->client()->post( 'v1/ideogram-v3/edit', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function repaint( Image $image, string $prompt, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'aspect_ratio', 'character_reference_images', 'character_reference_images_mask', 'color_palette',
            'image_weight', 'magic_prompt', 'negative_prompt', 'rendering_speed', 'resolution', 'seed',
            'style_codes', 'style_preset', 'style_reference_images', 'style_type'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );
        $files = $this->toFiles( $options, ['character_reference_images', 'character_reference_images_mask', 'style_reference_images'] );

        $request = $this->request( ['prompt' => $prompt] + $allowed, ['image' => $image] + $files );
        $response = $this->client()->post( 'v1/ideogram-v3/remix', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function upscale( Image $image, int $factor, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['detail', 'magic_prompt_option', 'prompt', 'resemblance', 'seed'] );
        $allowed = $this->sanitize( $allowed, $this->options() );

        $request = $this->request( ['image_request' => json_encode( $allowed )], ['image_file' => $image] );
        $response = $this->client()->post( 'upscale', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    /**
     * Returns the list of available options and their possible values.
     *
     * @return array<string, array<string>> List of option names and their possible values
     */
    protected function options() : array
    {
        return [
            'aspect_ratio' => [
                '1x3', '3x1', '1x2', '2x1', '9x16', '16x9', '10x16', '16x10',
                '2x3', '3x2', '3x4', '4x3', '4x5', '5x4', '1x1'
            ],
            'magic_prompt' => ['AUTO', 'ON', 'OFF'],
            'rendering_speed' => ['FLASH', 'TURBO', 'DEFAULT', 'QUALITY'],
            'style_preset' => [
                '80S_ILLUSTRATION', '90S_NOSTALGIA', 'ABSTRACT_ORGANIC', 'ANALOG_NOSTALGIA', 'ART_BRUT',
                'ART_DECO', 'ART_POSTER', 'AURA', 'AVANT_GARDE', 'BAUHAUS', 'BLUEPRINT', 'BLURRY_MOTION',
                'BRIGHT_ART', 'C4D_CARTOON', 'CHILDRENS_BOOK', 'COLLAGE', 'COLORING_BOOK_I', 'COLORING_BOOK_II',
                'CUBISM', 'DARK_AURA', 'DOODLE', 'DOUBLE_EXPOSURE', 'DRAMATIC_CINEMA', 'EDITORIAL',
                'EMOTIONAL_MINIMAL', 'ETHEREAL_PARTY', 'EXPIRED_FILM', 'FLAT_ART', 'FLAT_VECTOR', 'FOREST_REVERIE',
                'GEO_MINIMALIST', 'GLASS_PRISM', 'GOLDEN_HOUR', 'GRAFFITI_I', 'GRAFFITI_II', 'HALFTONE_PRINT',
                'HIGH_CONTRAST', 'HIPPIE_ERA', 'ICONIC', 'JAPANDI_FUSION', 'JAZZY', 'LONG_EXPOSURE',
                'MAGAZINE_EDITORIAL', 'MINIMAL_ILLUSTRATION', 'MIXED_MEDIA', 'MONOCHROME', 'NIGHTLIFE',
                'OIL_PAINTING', 'OLD_CARTOONS', 'PAINT_GESTURE', 'POP_ART', 'RETRO_ETCHING', 'RIVIERA_POP',
                'SPOTLIGHT_80S', 'STYLIZED_RED', 'SURREAL_COLLAGE', 'TRAVEL_POSTER', 'VINTAGE_GEO',
                'VINTAGE_POSTER', 'WATERCOLOR', 'WEIRD', 'WOODBLOCK_PRINT'
            ],
            'resolution' => [
                '512x1536', '576x1408', '576x1472', '576x1536', '640x1344', '640x1408', '640x1472',
                '640x1536', '704x1152', '704x1216', '704x1280', '704x1344', '704x1408', '704x1472',
                '736x1312', '768x1088', '768x1216', '768x1280', '768x1344', '800x1280', '832x960',
                '832x1024', '832x1088', '832x1152', '832x1216', '832x1248', '864x1152', '896x960',
                '896x1024', '896x1088', '896x1120', '896x1152', '960x832', '960x896', '960x1024',
                '960x1088', '1024x832', '1024x896', '1024x960', '1024x1024', '1088x768', '1088x832',
                '1088x896', '1088x960', '1120x896', '1152x704', '1152x832', '1152x864', '1152x896',
                '1216x704', '1216x768', '1216x832', '1248x832', '1280x704', '1280x768', '1280x800',
                '1312x736', '1344x640', '1344x704', '1344x768', '1408x576', '1408x640', '1408x704',
                '1472x576', '1472x640', '1472x704', '1536x512', '1536x576', '1536x640'
            ]
        ];
    }


    /**
     * Converts the HTTP response into a FileResponse instance.
     *
     * @param ResponseInterface $response Guzzle HTTP response
     * @return FileResponse File response instance
     */
    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $result = json_decode( $response->getBody(), true ) ?? [];
        $data = current( $result['data'] ?? [] ) ?: [];

        if( !isset( $data['url'] ) ) {
            throw new \Aimeos\Prisma\Exceptions\PrismaException( 'No image data found in response' );
        }

        return FileResponse::fromUrl( $data['url'] )
            ->withDescription( $data['prompt'] ?? null )
            ->withMeta( $data + ['created' => $result['created'] ?? null] );
    }


    /**
     * Converts the given options into a list of files.
     *
     * @param array<string, mixed> $options Associative list of name/value pairs
     * @param array<string> $names List of option names to convert
     * @return array<string, array<int, Image>> Associative list of file name/Image instances
     */
    protected function toFiles( array $options, array $names ) : array
    {
        $files = [];

        foreach( $names as $name )
        {
            foreach( (array) ( $options[$name] ?? [] ) as $file ) {
                $files[$name][] = $file;
            }
        }

        return $files;
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 422 )
        {
            $msg = json_decode( $response->getBody() )?->error ?: $response->getReasonPhrase();
            throw new \Aimeos\Prisma\Exceptions\ForbiddenException( $msg );
        }

        parent::validate( $response );
    }
}
