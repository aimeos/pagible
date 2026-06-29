<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('transcribe-audio')]
#[Title('Transcribe an audio file')]
#[Description('Transcribes the speech in an audio file using AI. Returns an array of segments, each with a start time, end time and the spoken text.')]
class TranscribeAudio extends Tool
{
    use Watch;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'audio:transcribe', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'file' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the audio file to transcribe.',
        ] );

        /** @var File|null $file */
        $file = File::select( 'id', 'path', 'mime' )->find( $v['file'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        if( !str_starts_with( (string) $file->mime, 'audio/' ) ) {
            return Response::structured( ['error' => sprintf( 'File type "%s" is not an audio file.', $file->mime )] );
        }

        $provider = config( 'cms.ai.transcribe.provider' );
        $config = config( 'cms.ai.transcribe', [] );
        $model = config( 'cms.ai.transcribe.model' );

        if( str_starts_with( (string) $file->path, 'http' ) ) {
            $doc = Audio::fromUrl( (string) $file->path, $file->mime );
        } else {
            $doc = Audio::fromStoragePath( (string) $file->path, config( 'cms.disk', 'public' ), $file->mime );
        }

        $data = Prisma::audio()
            ->observe( $this->observer( \Aimeos\Cms\Utils::editor( $request->user() ) ) )
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'transcribe' )
            ->transcribe( $doc, null, $config ) // @phpstan-ignore-line method.notFound
            ->structured();

        $segments = array_map( fn( $entry ) => [
            'start' => Utils::formatSeconds( $entry['start'] ),
            'end' => Utils::formatSeconds( $entry['end'] ),
            'text' => $entry['text'],
        ], $data );

        return Response::structured( ['segments' => $segments] );
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
                ->description( 'The UUID of the audio file to transcribe.' )
                ->required(),
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
        return Permission::can( 'audio:transcribe', $request->user() );
    }
}
