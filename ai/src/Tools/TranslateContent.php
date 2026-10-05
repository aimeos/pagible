<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('translate-content')]
#[Title('Translate text content')]
#[Description('Translates one or more texts from one language to another.
Formatting and parts that should not be translated must be removed and added again afterwards.
Returns the translated texts as a JSON array in the same order as the input.')]
class TranslateContent extends Tool
{
    protected const PERMISSIONS = ['text:translate'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $validated = $request->validate([
            'texts' => 'required|array|min:1|max:50',
            'texts.*' => 'string|max:10000',
            'to' => 'required|string|max:5',
            'from' => 'string|max:5',
            'context' => 'string|max:1000',
        ], [
            'texts.required' => 'You must provide an array of texts to translate.',
            'to.required' => 'You must specify the target language code, e.g., "de" or "fr".',
        ] );

        $translations = Ai::translate( $validated['texts'], $validated['to'], $validated['from'] ?? null,
            $validated['context'] ?? null );

        return Response::structured( ['translations' => $translations] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'texts' => $schema->array()
                ->description('Array of text strings to translate. Supports markdown formatting.')
                ->required(),
            'to' => $schema->string()
                ->description('Target language code, e.g., "de", "fr", "es", "ja". Use get-locales to see available languages.')
                ->required(),
            'from' => $schema->string()
                ->description('Source language code. Auto-detected if omitted.'),
            'context' => $schema->string()
                ->description('Additional context to improve translation quality, e.g., the topic or domain of the text.'),
        ];
    }
}
