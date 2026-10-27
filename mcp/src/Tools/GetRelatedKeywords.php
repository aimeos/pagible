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
#[Name('get-related-keywords')]
#[Title('Get related keywords')]
#[Description('Returns keywords related to a seed keyword including their average monthly search volume as list of "keyword" and "volume" entries ordered by volume. Use it to find keyword ideas, topics and search phrases for new or existing content. Data is cached for 7 days.')]
class GetRelatedKeywords extends Tool
{
    protected const PERMISSIONS = ['page:metrics'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'keyword' => 'required|string|max:80',
            'country' => 'nullable|string|alpha:ascii|size:2',
            'limit' => 'integer|min:1|max:100',
        ], [
            'keyword.required' => 'You must specify a seed keyword, e.g., "laravel cms".',
            'country.size' => 'The country must be a two letter ISO code, e.g., "us" or "de".',
        ] );

        $list = Keywords::related( $v['keyword'], $v['country'] ?? null, $v['limit'] ?? 50 );
        return Response::structured( ['keywords' => $list] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'keyword' => $schema->string()
                ->description('Seed keyword or search phrase, e.g., "laravel cms".')
                ->required(),
            'country' => $schema->string()
                ->description('Two letter ISO country code of the target market, e.g., "us" or "de". Omit it for worldwide data if the keyword service supports it.'),
            'limit' => $schema->integer()
                ->description('Maximum number of related keywords (1-100, default: 50).'),
        ];
    }
}
