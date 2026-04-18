<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Schema;


class SchemaResolver
{
    /**
     * Returns all registered theme schemas.
     *
     * @param mixed $rootValue
     * @param array<string, mixed> $args
     * @return array<int, array<string, mixed>>
     */
    public function schemas( $rootValue, array $args ) : array
    {
        $result = [];

        foreach( Schema::all() as $name => $theme )
        {
            $result[] = [
                'name' => $name,
                'label' => $theme['label'] ?? $name,
                'types' => $theme['types'] ?? null,
                'content' => $theme['content'] ?? null,
                'meta' => $theme['meta'] ?? null,
                'config' => $theme['config'] ?? null,
            ];
        }

        return $result;
    }
}
