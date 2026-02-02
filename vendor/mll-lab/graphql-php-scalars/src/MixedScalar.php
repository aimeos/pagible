<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

class MixedScalar extends ScalarType
{
    public string $name = 'Mixed';

    public ?string $description = /** @lang Markdown */ <<<'DESCRIPTION'
        Loose type that allows any value. Be careful when passing in large `Int` or `Float` literals,
        as they may not be parsed correctly on the server side. Use `String` literals if you are
        dealing with really large numbers to be on the safe side.
        DESCRIPTION;

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        return $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}
