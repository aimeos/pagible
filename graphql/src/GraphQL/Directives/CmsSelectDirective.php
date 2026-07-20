<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


final class CmsSelectDirective extends BaseDirective implements FieldBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Select only requested model columns for a relation.
"""
directive @cmsSelect(
  "Column required by Eloquent to match related models"
  key: String!

  "Selectable GraphQL fields which map directly to model columns"
  columns: [String!]!
) on FIELD_DEFINITION
GRAPHQL;
    }


    public function handleFieldBuilder( QueryBuilder|EloquentBuilder|Relation $builder, mixed $root,
        array $args, GraphQLContext $context, ResolveInfo $resolveInfo ): QueryBuilder|EloquentBuilder|Relation
    {
        $fields = array_keys( $resolveInfo->getFieldSelection() );
        $columns = array_intersect( $this->directiveArgValue( 'columns' ), $fields );
        $builder->select( array_values( array_unique( [$this->directiveArgValue( 'key' ), ...$columns] ) ) );

        return $builder;
    }
}
