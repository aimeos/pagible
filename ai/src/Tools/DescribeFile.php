<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Utils;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('describe-file')]
#[Title('Describe a media file using AI')]
#[Description('Generates a textual description/summary of an image, audio or video file using AI. Useful for alt texts, captions or content summaries. Returns the description as text.')]
class DescribeFile extends Tool
{
    use ObservesPrisma;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:describe', $request->user() )
            || !Permission::can( 'file:view', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'lang' => 'nullable|string|max:5',
        ], [
            'file.required' => 'You must specify the UUID of the file to describe.',
        ] );

        /** @var File|null $file */
        $file = File::select( 'id', 'path', 'mime' )->find( $v['file'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        $type = explode( '/', (string) $file->mime, 2 )[0];
        $class = '\\Aimeos\\Prisma\\Files\\' . ucfirst( $type );

        if( !class_exists( $class ) ) {
            return Response::structured( ['error' => sprintf( 'Unsupported file type "%s".', $file->mime )] );
        }

        $provider = config( 'cms.ai.describe.provider' );
        $config = config( 'cms.ai.describe', [] );
        $model = config( 'cms.ai.describe.model' );

        if( str_starts_with( (string) $file->path, 'http' ) ) {
            $doc = $class::fromUrl( (string) $file->path, $file->mime );
        } else {
            $doc = $class::fromStoragePath( (string) $file->path, config( 'cms.disk', 'public' ), $file->mime );
        }

        $text = Prisma::type( $type )->observe( $this->observer( Utils::editor( $request->user() ) ) )
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'describe' )
            ->describe( $doc, $v['lang'] ?? null, $config ) // @phpstan-ignore-line method.notFound
            ->text();

        return Response::structured( ['description' => $text] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image, audio or video file to describe.' )
                ->required(),
            'lang' => $schema->string()
                ->description( 'ISO language code the description should be written in, e.g., "en" or "de".' ),
        ];
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'file:describe', $request->user() )
            && Permission::can( 'file:view', $request->user() );
    }
}
