<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Directives;

use Aimeos\Cms\Permission;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


class CmsPermissionDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Check CMS permissions for the authenticated user.
"""
directive @cmsPermission(
  "The permission action to check, e.g. 'page:add' or 'image:imagine'"
  action: String!
) on FIELD_DEFINITION
GRAPHQL;
    }


    public function handleField( FieldValue $fieldValue ): void
    {
        $fieldValue->wrapResolver( fn( callable $resolver ): \Closure =>
            function( mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo ) use ( $resolver ) {
                $action = $this->directiveArgValue( 'action' );

                if( !Permission::can( $action, Auth::user() ) ) {
                    throw new Error( 'Insufficient permissions' );
                }

                return $resolver( $root, $args, $context, $resolveInfo );
            }
        );
    }
}
