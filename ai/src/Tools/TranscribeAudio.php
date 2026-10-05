<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Audio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('transcribe-audio')]
#[Title('Transcribe an audio file')]
#[Description('Transcribes the speech in an audio file using AI. Returns an array of segments, each with a start time, end time and the spoken text.')]
class TranscribeAudio extends Tool
{
    protected const PERMISSIONS = ['audio:transcribe', 'file:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate( [
            'file' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the audio file to transcribe.',
        ] );

        $audio = Ai::files( [$v['file']], Audio::class )[0]
            ?? throw new \Aimeos\Cms\Exception( 'Audio file not found or not an audio file.' );

        return Response::structured( ['segments' => Ai::transcribe( $audio )] );
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
}
