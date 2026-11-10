<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\PageView;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Schema\Schema as PrismaSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;


/**
 * Refines content elements and structured entries like "meta" using AI.
 */
final class Refiner
{
    /**
     * AI operation refining the content.
     */
    private const OP = 'refine';

    /**
     * Maximum content bytes of all steps together, each step resends the schema and content.
     */
    private const BYTES = 512 * 1024;

    /**
     * Maximum steps if lookup tools are used, fewer than the chat as each step resends the content.
     */
    private const STEPS = 5;


    /**
     * Refines page content based on a prompt.
     *
     * @param string $prompt Description of the changes
     * @param array<mixed> $content Content elements to refine
     * @param string|null $pagetype Page type to limit the content element types
     * @param string|null $context Additional context like audience or tone
     * @param string|null $lang ISO language code of the refined content
     * @return array<mixed> Refined content elements
     * @throws Exception If the AI response is invalid
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function content( string $prompt, array $content, ?string $pagetype = null, ?string $context = null, ?string $lang = null ) : array
    {
        $system = self::system( 'content', $context, $lang );
        $structured = self::refined( $system, $prompt, $content, JsonSchema::build( 'content', $pagetype ), Toolset::READ );
        return self::merge( $content, $structured['contents'] ?? [], $pagetype );
    }


    /**
     * Refines the structured entries of a schema section like "meta" or "config" based on a prompt.
     *
     * @param string $prompt Description of the changes
     * @param array<mixed> $entries Structured entries to refine
     * @param string $type Schema section, e.g. "meta"
     * @param string|null $context Additional context like audience or tone
     * @param string|null $lang ISO language code of the refined entries
     * @return array<mixed> Refined entries
     * @throws Exception If the AI response is invalid
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function data( string $prompt, array $entries, string $type, ?string $context = null, ?string $lang = null ) : array
    {
        $system = self::system( $type, $context, $lang );
        $structured = self::refined( $system, $prompt, $entries, JsonSchema::build( $type ) );
        return self::entries( $entries, $structured, $type );
    }


    /**
     * Refines the draft content of a stored page based on a prompt.
     *
     * Trashed pages can be refined too, so they can be restored with new content.
     *
     * @param string $id Page UUID
     * @param string $prompt Description of the changes
     * @param string|null $context Additional context like audience or tone
     * @param string|null $lang Language code of the variant and of the refined content
     * @return array<mixed> Refined content elements
     * @throws ModelNotFoundException If the page doesn't exist
     * @throws Exception If the AI response is invalid
     * @throws PrismaException If no provider is configured or the request fails
     */
    public static function page( string $id, string $prompt, ?string $context = null, ?string $lang = null ) : array
    {
        $query = PageView::withTrashed();
        $query = $lang !== null ? $query->fallback( $lang, true ) : $query->language( null, true );
        $page = $query->select( 'id', 'tenant_id', 'type', 'content', 'latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'aux', 'data->type as type' )] )
            ->findOrFail( $id );

        $content = (array) ( $page->latestAux( 'content' ) ?? [] );

        return self::content( $prompt, $content, $page->latest->type ?? $page->type, $context, $lang );
    }


    /**
     * Merges the refined data into a content element and updates its file IDs.
     *
     * @param array<string, mixed> $entry Existing content element
     * @param array<mixed> $item Refined content element
     * @return array<string, mixed> Updated content element
     */
    private static function element( array $entry, array $item ) : array
    {
        $refined = array_filter( (array) ( $item['data'] ?? [] ), fn( $v ) => $v !== null );
        $entry['data'] = (array) Validation::defaults( $entry['type'], array_merge( (array) ( $entry['data'] ?? [] ), $refined ) );

        if( $ids = Validation::files( $entry['data'] ) ) {
            $entry['files'] = $ids;
        } else {
            unset( $entry['files'] );
        }

        return $entry;
    }


    /**
     * Merges an AI refine response into the structured entries of a schema section like "meta".
     *
     * @param array<mixed> $entries Existing entries by name
     * @param array<mixed> $response AI response with updated entries by name
     * @param string $type Schema section, e.g. "meta"
     * @return array<mixed> Updated and validated entries
     */
    private static function entries( array $entries, array $response, string $type ) : array
    {
        foreach( $response as $key => $data )
        {
            if( is_array( $data ) ) {
                $entries[$key] = Validation::entry( $key, array_filter( $data, fn( $v ) => $v !== null ), $type );
            }
        }

        return (array) Validation::structured( $entries, $type );
    }


    /**
     * Merges an AI refine response into the existing content elements.
     *
     * Keeps IDs and groups of existing elements and derives the file IDs from the element data.
     *
     * @param array<mixed> $content Existing content elements
     * @param array<mixed> $response AI response with updated content elements
     * @param string|null $pagetype Page type whose sections provide the default group
     * @return array<mixed> Updated content elements
     */
    private static function merge( array $content, array $response, ?string $pagetype = null ) : array
    {
        $result = [];
        $map = collect( $content )->keyBy( 'id' );
        $default = Schema::section( $pagetype );

        foreach( $response as $item )
        {
            if( !is_array( $item ) ) {
                continue;
            }

            // pull() returns the whole map for a NULL key
            $id = $item['id'] ?? null;
            $entry = is_string( $id ) && $id !== '' ? (array) $map->pull( $id, [] ) : [];
            // data can't be merged into another type
            $entry['type'] = $entry['type'] ?? $item['type'] ?? 'text';
            $entry['id'] = ( $entry['id'] ?? '' ) ?: Utils::uid();
            $entry['group'] = $entry['group'] ?? $item['group'] ?? $default;

            $result[] = $entry['type'] === 'reference' ? self::reference( $entry, $item ) : self::element( $entry, $item );
        }

        return $result;
    }


    /**
     * Updates the referenced element ID of a reference element.
     *
     * @param array<string, mixed> $entry Existing reference element
     * @param array<mixed> $item Refined reference element
     * @return array<string, mixed> Updated reference element
     */
    private static function reference( array $entry, array $item ) : array
    {
        $refid = $item['refid'] ?? $entry['refid'] ?? null;

        if( $refid !== null ) {
            $entry['refid'] = $refid;
        }

        unset( $entry['data'] );
        return $entry;
    }


    /**
     * Returns the structured content refined by the AI provider.
     *
     * @param string $system System prompt returned by system()
     * @param string $prompt Description of the changes
     * @param array<mixed> $content Content to refine
     * @param array<string, mixed> $schema JSON schema of the response
     * @param array<int, string> $tools MCP tool classes the provider may call
     * @return array<mixed> Structured content matching the schema
     * @throws Exception If the AI response is invalid
     */
    private static function refined( string $system, string $prompt, array $content, array $schema, array $tools = [] ) : array
    {
        $json = AiClient::checkInput( $content );
        $response = PrismaSchema::fromArray( 'response', $schema );
        $input = $prompt . "\n\nContent as JSON:\n" . $json;
        $steps = min( AiClient::steps(), self::STEPS, intdiv( self::BYTES, max( 1, strlen( $json ) ) ) );

        // tools need a second step, so large content is refined without them
        $structured = $tools && $steps > 1
            ? self::request( $system, $input, $response, $tools, $steps )
            : self::request( $system, $input, $response );

        if( !$structured ) {
            throw new Exception( __( 'No structured content returned in refine response' ) );
        }

        if( $errors = $response->validate( $structured ) )
        {
            Log::warning( 'Invalid refine response', ['errors' => $errors] );

            $msg = config( 'app.debug' )
                ? __( 'Invalid refine response: :errors', ['errors' => implode( '; ', $errors )] )
                : __( 'Invalid content in refine response' );

            throw new Exception( $msg );
        }

        return $structured;
    }


    /**
     * Returns the structured response of the "refine" provider which may use the passed tools.
     *
     * @param string $system System prompt
     * @param string $prompt Prompt including the content to refine
     * @param PrismaSchema $schema Schema of the response
     * @param array<int, string> $tools MCP tool classes the provider may call
     * @param int $steps Maximum number of provider requests for the tool calls and the answer
     * @return array<mixed>|null Structured response or NULL if none is returned
     * @throws PrismaException If no provider is configured or structured responses aren't supported
     */
    private static function request( string $system, string $prompt, PrismaSchema $schema, array $tools = [], int $steps = 1 ) : ?array
    {
        return AiClient::lifted( fn() => AiClient::prompt( self::OP, $system, $tools, $steps )
            ->structure( $prompt, $schema, [], AiClient::options( self::OP ) ) // @phpstan-ignore-line method.notFound
            ->structured(), $steps );
    }


    /**
     * Returns the system prompt for refining the content of the schema section.
     *
     * @param string $type Schema section, e.g. "content" or "meta"
     * @param string|null $context Additional context like audience or tone
     * @param string|null $lang ISO language code of the refined content
     * @return string System prompt
     */
    private static function system( string $type, ?string $context, ?string $lang ) : string
    {
        return AiClient::system( view( 'cms::prompts.refine', ['type' => $type, 'lang' => $lang] )->render(), $context );
    }
}
