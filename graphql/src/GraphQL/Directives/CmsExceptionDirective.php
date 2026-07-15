<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Directives;

use Aimeos\Cms\Exception as CmsException;
use Aimeos\Cms\GraphQL\ClientException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


final class CmsExceptionDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Convert CMS exceptions to client-safe GraphQL errors.
"""
directive @cmsException on FIELD_DEFINITION
GRAPHQL;
    }


    public function handleField( FieldValue $fieldValue ): void
    {
        $fieldValue->wrapResolver( static fn( callable $resolver ): \Closure =>
            static function( mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo ) use ( $resolver ) {
                try {
                    return $resolver( $root, $args, $context, $resolveInfo );
                } catch( CmsException $e ) {
                    throw new ClientException( $e );
                }
            }
        );
    }
}
