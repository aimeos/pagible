<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Type\Definition\ScalarType;

class NullScalar extends ScalarType
{
    public const ONLY_NULL_IS_ALLOWED = 'Only null is allowed.';

    public string $name = 'Null';

    public ?string $description /** @lang Markdown */
        = 'Always `null`. Strictly validates value is non-null, no coercion.';

    public function serialize($value)
    {
        if ($value !== null) {
            throw new InvariantViolation(self::ONLY_NULL_IS_ALLOWED);
        }

        return null;
    }

    public function parseValue($value)
    {
        if ($value !== null) {
            throw new Error(self::ONLY_NULL_IS_ALLOWED);
        }

        return null;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (! $valueNode instanceof NullValueNode) {
            throw new Error(self::ONLY_NULL_IS_ALLOWED);
        }

        return null;
    }
}
