<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('describe-file')]
#[Title('Describe a media file using AI')]
#[Description('Generates a textual description/summary of an image, audio or video file using AI. Useful for alt texts, captions or content summaries. Returns the description as text.')]
class DescribeFile extends Tool
{
    protected const PERMISSIONS = ['file:describe', 'file:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'lang' => 'nullable|string|max:5',
        ], [
            'file.required' => 'You must specify the UUID of the file to describe.',
        ] );

        return Response::structured( ['description' => Ai::describe( (string) $v['file'], $v['lang'] ?? null )] );
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
}
