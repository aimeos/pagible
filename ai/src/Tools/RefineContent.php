<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Refiner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('refine-content')]
#[Title('Refine page content using AI')]
#[Description('Improves or restructures existing page content using AI based on a prompt. Pass the page ID and a prompt describing the changes. Returns the refined content elements as a JSON array.')]
class RefineContent extends Tool
{
    protected const PERMISSIONS = ['page:refine', 'page:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description( 'The UUID of the page whose content to refine.' )
                ->required(),
            'prompt' => $schema->string()
                ->description( 'Describe how to improve the content, e.g., "Make the text more engaging and add subheadings" or "Rewrite for a technical audience".' )
                ->required(),
            'context' => $schema->string()
                ->description( 'Additional context such as target audience, tone, or brand guidelines.' ),
            'lang' => $schema->string()
                ->description( 'Language code of the page variant to refine and of the refined content, e.g. "en" or "de". The source content is refined if the page has no variant in this language.' ),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( [
            'id' => 'required|string|max:36',
            'prompt' => 'required|string|max:2000',
            'context' => 'nullable|string|max:30000',
            'lang' => 'nullable|string|max:10|cms_locale',
        ], [
            'id.required' => 'You must specify the ID of the page to refine.',
            'prompt.required' => 'You must provide a prompt describing how to refine the content.',
        ] );

        $result = Refiner::page( (string) $v['id'], $v['prompt'], $v['context'] ?? null, $v['lang'] ?? null );

        return Response::structured( ['content' => $result] );
    }
}
