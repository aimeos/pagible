<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Validation;
use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('add-page')]
#[Title('Create a new page within the page tree')]
#[Description('Creates a new page in the page tree. Requires name (max 50 chars), title (max 100 chars), content (array of {type, data} objects — use get-schemas for types), and canonical meta entries with meta-tags.data.description for SEO. Meta and config entries must contain type, data, and files. Files and shared elements are attached automatically. Optional: lang (ISO code like "en", the source language, defaults to the application locale), config, to, tag, theme, type, domain, path, cache (minutes), parent_id, ref. Returns the created page as JSON, including the latest_id to pass to save-page when editing it.')]
class AddPage extends Tool
{
    protected const PERMISSIONS = ['page:add'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate( [
            ...SavePage::RULES,
            'lang' => 'string|max:10',
            'name' => 'required|string|max:50',
            'title' => 'required|string|max:100',
            'content' => 'required|array',
            'content.*.data' => 'required|array',
            'meta' => 'required|array',
            'meta.meta-tags' => 'required|array:type,data,files',
            'meta.meta-tags.data.description' => 'required|string|max:300',
            'parent_id' => 'string|max:36',
            'ref' => 'string|max:36',
        ], [
            'name.required' => 'You must specify a name for the page and it must not be longer than 50 characters.',
            'title.required' => 'You must specify a page title and it must not be longer than 100 characters.',
            'content.required' => 'You must provide content elements for the page. Use get-schemas for available types.',
            'meta.required' => 'You must provide meta data with at least a meta-tags description for SEO.',
            'meta.meta-tags.required' => 'You must provide meta-tags with a description for SEO.',
            'meta.meta-tags.data.description.required' => 'You must provide a meta description in meta.meta-tags.data.description for SEO. It should be 150-160 characters.',
        ] );

        $pid = $v['parent_id'] ?? null;

        /** @var Page|null $parent */
        $parent = $pid
            ? Page::withTrashed()
                ->select( 'id', 'tenant_id', 'latest_id' )
                ->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'data' )] )
                ->find( $pid )
            : null;

        $v['path'] = $v['path'] ?? Utils::slugify( $v['title'] );
        $v['domain'] = $v['domain'] ?? $parent?->latest?->data->domain ?? '';
        $v['theme'] = $v['theme'] ?? $parent?->latest?->data->theme ?? '';
        $v['type'] = $v['type'] ?? $parent?->latest?->data->type ?? '';

        // Use the raw request input, not the validated copy: validate() rebuilds
        // wildcard arrays via rule expansion, which materializes elements matched
        // by content.*.id first and appends id-less elements at the end, scrambling
        // the author's order. The raw input preserves it; Validation::content()
        // still whitelists each element's keys.
        $v['content'] = Validation::content( $request->get( 'content' ), $v['type'] );
        $v['lang'] = ( $v['lang'] ?? null ) ?: (string) config( 'app.locale', 'en' );
        $v['cache'] = $v['cache'] ?? 5;
        $v['tag'] = $v['tag'] ?? '';
        $v['to'] = $v['to'] ?? '';
        $v['status'] = 0;

        $input = array_diff_key( $v, array_flip( ['parent_id', 'ref'] ) );

        $page = Resource::addPage(
            $input,
            $request->user(),
            $v['ref'] ?? null,
            $pid,
        );

        return Response::structured( Presenter::item( $page ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'lang' => $schema->string()
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US". It becomes the source language other languages are translated from. Defaults to the application locale.'),
            'name' => $schema->string()
                ->description('Short name of the page for menus in the language of the page. Should not be longer than 30 characters.')
                ->required(),
            'title' => $schema->string()
                ->description('Engaging and SEO optimized page title in the language of the page. Must be unique for each page and not longer than 60 characters.')
                ->required(),
            'content' => $schema->array()
                ->items( $schema->object( [
                    'id' => $schema->string()
                        ->description( 'Optional element ID. Auto-generated if omitted.' ),
                    'type' => $schema->string()
                        ->description( 'Content element type. Use get-schemas for available types.' )
                        ->required(),
                    'group' => $schema->string()
                        ->description( 'Layout section, e.g., "main", "footer". Use "main" if unsure.' )
                        ->required(),
                    'data' => $schema->object()
                        ->description( 'Field values for this element. Use get-schemas for available fields per type.' )
                        ->required(),
                ] ) )
                ->description( 'Content elements. Use get-schemas for available types and fields.' )
                ->required(),
            'meta' => $schema->object()
                ->description( 'Canonical meta entries keyed by type. Every entry must contain matching type, data, and files. Must include meta-tags.data.description (150-160 chars).' )
                ->required(),
            'config' => $schema->object()
                ->description( 'Canonical configuration entries keyed by type. Every entry must contain matching type, data, and files.' ),
            'to' => $schema->string()
                ->description( 'Redirect URL. If set, the page redirects to this URL instead of rendering content.' ),
            'tag' => $schema->string()
                ->description( 'Tag name to identify a page, e.g., for the starting point of a navigation structure.' ),
            'theme' => $schema->string()
                ->description( 'Theme name assigned to the page. Inherited from parent if omitted.' ),
            'type' => $schema->string()
                ->description( 'Page type for using different theme templates.' ),
            'domain' => $schema->string()
                ->description( 'Domain name the page is assigned to. Inherited from parent if omitted.' ),
            'path' => $schema->string()
                ->description( 'Unique URL segment. Auto-generated from title if omitted.' ),
            'cache' => $schema->integer()
                ->description( 'Cache lifetime in minutes. Default: 5.' ),
            'parent_id' => $schema->string()
                ->description( 'ID of the parent page where the new page will be added below.' ),
            'ref' => $schema->string()
                ->description( 'ID of a sibling page to insert before. Takes priority over parent_id positioning.' ),
        ];
    }
}
