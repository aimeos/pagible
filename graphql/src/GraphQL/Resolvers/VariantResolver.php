<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Models\Page;
use GraphQL\Deferred;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


/**
 * Resolves the language variants of pages in batches.
 */
final class VariantResolver
{
    /** @var array<string, string> Source languages keyed by page ID */
    private array $pages = [];

    /** @var array<string, list<array<string, mixed>>>|null Variant lists keyed by page ID */
    private ?array $result = null;


    /**
     * Returns the variant list of the page, loaded together with the other pages at the same level.
     *
     * @param array<string, mixed> $args
     */
    public function __invoke( Page $page, array $args, GraphQLContext $context, ResolveInfo $info ) : Deferred
    {
        $loader = BatchLoaderRegistry::instance( $info->path, fn() => new self() );
        $id = (string) $page->id;
        $loader->pages[$id] = (string) $page->source;

        return new Deferred( function() use ( $loader, $id ) {
            return ( $loader->result ??= Page::variantLists( $loader->pages ) )[$id] ?? [];
        } );
    }
}
