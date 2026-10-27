<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Keywords;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[IsOpenWorld]
#[Name('get-keyword-volume')]
#[Title('Get search volume of keywords')]
#[Description('Returns the average monthly search volume of up to 100 keywords as list of "keyword" and "volume" entries. The volume is null if no data is available for a keyword. Use it to choose the keywords for titles, headlines, texts and meta data with the highest search demand. Data is cached for 7 days.')]
class GetKeywordVolume extends Tool
{
    protected const PERMISSIONS = ['page:metrics'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'keywords' => 'required|array|min:1|max:100',
            'keywords.*' => 'required|string|max:80',
            'country' => 'nullable|string|alpha:ascii|size:2',
        ], [
            'keywords.required' => 'You must specify a list of keywords, e.g., ["laravel cms", "headless cms"].',
            'country.size' => 'The country must be a two letter ISO code, e.g., "us" or "de".',
        ] );

        return Response::structured( ['keywords' => Keywords::volume( $v['keywords'], $v['country'] ?? null )] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'keywords' => $schema->array()
                ->items( $schema->string()->max( 80 ) )
                ->description('List of up to 100 keywords or search phrases, e.g., ["laravel cms", "headless cms"].')
                ->required(),
            'country' => $schema->string()
                ->description('Two letter ISO country code of the target market, e.g., "us" or "de". Omit it for worldwide data if the keyword service supports it.'),
        ];
    }
}
