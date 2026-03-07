<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('translate-content')]
#[Title('Translate text content')]
#[Description('Translates one or more texts from one language to another.
Formatting and parts that should not be translated must be removed and added again afterwards.
Returns the translated texts as a JSON array in the same order as the input.')]
class TranslateContent extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'text:translate', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

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

        $provider = config( 'cms.ai.translate.provider' );
        $config = config( 'cms.ai.translate', [] );
        $model = config( 'cms.ai.translate.model' );

        $texts = $validated['texts'];
        $to = $validated['to'];
        $from = $validated['from'] ?? null;
        $context = $validated['context'] ?? null;

        $translations =  Prisma::text()
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'translate' )
            ->translate( $texts, $to, $from, $context, $config ) // @phpstan-ignore-line method.notFound
            ->texts();

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


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'text:translate', $request->user() );
    }
}
