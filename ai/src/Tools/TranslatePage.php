<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Jobs\TranslatePage as Job;
use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('translate-page')]
#[Title('Translate a page into other languages')]
#[Description('Translates the source language variant of a page into one or more languages, like the Translate action of the editor.
Missing language variants are created, existing ones get the changes of the source merged into a new draft. Nothing is published.
Translations run in the background if a queue is configured. Returns the number of queued, finished and failed translations.
Use this instead of copying content with save-page, which breaks the synchronization of the content elements.')]
class TranslatePage extends Tool
{
    protected const PERMISSIONS = ['page:save'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'lang' => 'required|array|min:1|max:100',
            'lang.*' => 'string|max:10',
        ], [
            'id.required' => 'You must specify the ID of the page to translate.',
            'lang.required' => 'You must specify an array of language codes to translate the page into, e.g., ["de", "fr"].',
        ] );

        $page = Page::findOrFail( (string) $v['id'] );
        $langs = array_values( array_unique( $v['lang'] ) );
        $batch = Job::dispatchBatch( [(string) $page->id], $langs, $request->user()?->getAuthIdentifier() );

        return Response::structured( ['id' => $page->id, 'lang' => $langs] + ( Job::progress( $batch['id'] ) ?? [] ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description('UUID of the page to translate.')
                ->required(),
            'lang' => $schema->array()
                ->items( $schema->string() )
                ->description('ISO language codes to translate the page into, e.g., ["de", "fr"]. Use get-locales to see available languages.')
                ->required(),
        ];
    }
}
