# Pagible MCP

MCP server for [Pagible CMS](https://pagible.com) with 41 tools for managing pages, elements, files, frontend access (30 core tools), and AI-powered content operations (11 tools provided by the AI package). See [MCP.md](MCP.md) for setup and usage.

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible). For full installation, use:

```bash
composer require aimeos/pagible
```

## Custom tools

Your application can offer its own tools next to the CMS tools, e.g. to give agents access to your orders. Extend `Aimeos\Cms\Tools\Tool`, which checks the permissions in `PERMISSIONS` before calling `run()` and hides the tool from users without them (an empty list denies access to everyone):

```php
namespace App\Mcp;

use Aimeos\Cms\Tools\Tool;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('get-orders')]
#[Description('Returns the latest orders with their ID, status and total.')]
class GetOrders extends Tool
{
    protected const PERMISSIONS = ['order:view'];

    public function schema( JsonSchema $schema ) : array
    {
        return [
            'limit' => $schema->integer()->description( 'Number of orders, 10 by default' ),
        ];
    }

    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( ['limit' => 'nullable|integer|min:1|max:50'] );
        $orders = Order::latest()->limit( $v['limit'] ?? 10 )->get( ['id', 'status', 'total'] );

        return Response::structured( ['orders' => $orders->toArray()] );
    }
}
```

Register the tool and its permission in the `boot()` method of a service provider:

```php
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Permission;

Permission::register( 'order:view' );
CmsServer::register( [\App\Mcp\GetOrders::class] );
```

Users with the `admin` role get new permissions by its `*` wildcard. Grant them to others by `Permission::set()` or add them to a role in `cms.roles`. Within `run()`, `$request->user()` is the authenticated user, so pass it to the facades in `Aimeos\Cms\Facades` to apply the CMS permissions to content changes too. Thrown `Aimeos\Cms\Exception` errors and missing models are returned as `error` message to the client.

Registered tools are offered by the MCP server only, the AI chat of the admin panel uses the built-in CMS tools.

## Commands

### cms:install:mcp

Installs the Pagible MCP package.

```bash
php artisan cms:install:mcp
```

Publishes the Laravel MCP routes.

## License

MIT
