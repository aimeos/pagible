<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Refiner;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Tools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('refine-content')]
#[Title('Refine page content using AI')]
#[Description('Improves or restructures existing page content using AI based on a prompt. Pass the page ID and a prompt describing the changes. Returns the refined content elements as a JSON array.')]
class RefineContent extends Tool
{
    use Watch;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:refine', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
            'prompt' => 'required|string|max:2000',
            'context' => 'string|max:30000',
            'lang' => 'string|max:10',
        ], [
            'id.required' => 'You must specify the ID of the page to refine.',
            'prompt.required' => 'You must provide a prompt describing how to refine the content.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->select( 'id', 'type', 'content', 'latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'aux' )] )
            ->find( $validated['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        $content = (array) ( $page->latest?->aux->content ?? $page->content ?? [] );

        $provider = config( 'cms.ai.refine.provider' );
        $config = config( 'cms.ai.refine', [] );
        $model = config( 'cms.ai.refine.model' );

        $system = view( 'cms::prompts.refine' )->render();
        $schema = \Aimeos\Prisma\Schema\Schema::fromArray( 'response', \Aimeos\Cms\JsonSchema::build( 'content', $page->type ) );

        set_time_limit( (int) config( 'cms.ai.timeout' ) ); // long AI call; lift PHP's default 30s execution limit

        $response = Prisma::text()
            ->observe( $this->observer( \Aimeos\Cms\Utils::editor( $request->user() ) ) )
            ->using( $provider, $config )
            ->model( $model )
            ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
            ->withSystemPrompt( $system . "\n" . ( $validated['context'] ?? '' ) . ( !empty( $validated['lang'] ) ? "\nWrite the content in language: " . $validated['lang'] : '' ) )
            ->withClientOptions( [
                'timeout' => (int) config( 'cms.ai.timeout' ),
                'connect_timeout' => 10,
            ] )
            ->ensure( 'structure' )
            ->structure( $validated['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ), $schema, [], ['mode' => 'json'] ); // @phpstan-ignore-line method.notFound

        $structured = $response->structured();

        if( !$structured || $schema->validate( $structured ) ) {
            return Response::structured( ['error' => 'Invalid content in refine response.'] );
        }

        $result = Refiner::merge( $content, $structured['contents'] ?? [], $page->type );

        return Response::structured( ['content' => $result] );
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
                ->description('The UUID of the page whose content to refine.')
                ->required(),
            'prompt' => $schema->string()
                ->description('Describe how to improve the content, e.g., "Make the text more engaging and add subheadings" or "Rewrite for a technical audience".')
                ->required(),
            'context' => $schema->string()
                ->description('Additional context such as target audience, tone, or brand guidelines.'),
            'lang' => $schema->string()
                ->description('Language code the refined content should be written in, e.g. "en" or "de".'),
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
        return Permission::can( 'page:refine', $request->user() );
    }
}
