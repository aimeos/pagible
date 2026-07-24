<?php

namespace Aimeos\Cms\Mcp;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;


#[Name('CMS Server')]
#[Version('1.1.0')]
#[Instructions('This server provides access to the content management system.')]
class CmsServer extends Server
{
    public int $defaultPaginationLength = 50;


    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Read tools - Discovery & configuration
        \Aimeos\Cms\Tools\GetAccess::class,
        \Aimeos\Cms\Tools\GetLocales::class,
        \Aimeos\Cms\Tools\GetSchemas::class,

        // Read tools - Pages
        \Aimeos\Cms\Tools\GetPage::class,
        \Aimeos\Cms\Tools\GetPageTree::class,
        \Aimeos\Cms\Tools\GetPageHistory::class,
        \Aimeos\Cms\Tools\GetPageMetrics::class,
        \Aimeos\Cms\Tools\SearchPages::class,

        // Read tools - Elements
        \Aimeos\Cms\Tools\GetElement::class,
        \Aimeos\Cms\Tools\SearchElements::class,

        // Read tools - Files
        \Aimeos\Cms\Tools\GetFile::class,
        \Aimeos\Cms\Tools\SearchFiles::class,

        // Write tools - Pages
        \Aimeos\Cms\Tools\AddPage::class,
        \Aimeos\Cms\Tools\SavePage::class,
        \Aimeos\Cms\Tools\SetPageAccess::class,
        \Aimeos\Cms\Tools\PublishPage::class,
        \Aimeos\Cms\Tools\DropPage::class,
        \Aimeos\Cms\Tools\RestorePage::class,
        \Aimeos\Cms\Tools\MovePage::class,

        // Write tools - Elements
        \Aimeos\Cms\Tools\AddElement::class,
        \Aimeos\Cms\Tools\SaveElement::class,
        \Aimeos\Cms\Tools\PublishElement::class,
        \Aimeos\Cms\Tools\DropElement::class,
        \Aimeos\Cms\Tools\RestoreElement::class,

        // Write tools - Files
        \Aimeos\Cms\Tools\AddFile::class,
        \Aimeos\Cms\Tools\SaveFile::class,
        \Aimeos\Cms\Tools\PublishFile::class,
        \Aimeos\Cms\Tools\DropFile::class,
        \Aimeos\Cms\Tools\RestoreFile::class,

    ];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected static array $registered = [];


    /**
     * Register additional tools with the MCP server.
     *
     * @param array<int, class-string<\Laravel\Mcp\Server\Tool>> $tools
     */
    public static function register( array $tools ) : void
    {
        static::$registered = array_merge( static::$registered, $tools );
    }


    protected function boot() : void
    {
        $this->tools = array_merge( $this->tools, static::$registered );

        // Tag content changes made through MCP tools as 'mcp' for the audit log.
        Utils::source( 'mcp' );
    }


    protected function runMethodHandle( JsonRpcRequest $request, ServerContext $context ): iterable|JsonRpcResponse
    {
        if( $request->method !== 'tools/call' || !is_string( $request->get( 'name' ) ) ) {
            return parent::runMethodHandle( $request, $context );
        }

        $start = hrtime( true );
        $action = $request->get( 'name' );

        try {
            $response = parent::runMethodHandle( $request, $context );
        } catch( \Throwable $e ) {
            $this->record( (string) $action, $start, false );
            throw $e;
        }

        $this->record( (string) $action, $start, $this->success( $response ) );

        return $response;
    }


    protected function record( string $action, int|float $start, bool $success ) : void
    {
        Watch::observe(
            source: 'mcp',
            action: $action,
            durationMs: Watch::duration( $start ),
            dimensions: [
                'domain' => config( 'cms.multidomain' ) ? request()->getHost() : '',
                'success' => $success,
            ],
        );
    }


    /**
     * @param iterable<JsonRpcResponse>|JsonRpcResponse $response
     */
    protected function success( iterable|JsonRpcResponse $response ) : bool
    {
        if( !$response instanceof JsonRpcResponse ) {
            return true;
        }

        $payload = $response->toArray();

        return !isset( $payload['error'] ) && !data_get( $payload, 'result.isError', false );
    }
}
