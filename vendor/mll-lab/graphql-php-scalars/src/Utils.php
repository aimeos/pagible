<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Utils\Utils as GraphQLUtils;

final class Utils
{
    /** Check if a value can be serialized to a string. */
    public static function canBeString(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * Get the underlying string from a GraphQL literal and throw if Literal is not a string.
     *
     * @param Node&ValueNode $valueNode
     *
     * @throws Error
     */
    public static function extractStringFromLiteral(Node $valueNode): string
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error("Query error: Can only parse strings got: {$valueNode->kind}", $valueNode);
        }

        return $valueNode->value;
    }

    /**
     * Convert the value to a string or throw.
     *
     * @template T of \Throwable
     *
     * @param class-string<T> $exceptionClass
     *
     * @throws T
     */
    public static function coerceToString(mixed $value, string $exceptionClass): string
    {
        if (! self::canBeString($value)) {
            $safeValue = GraphQLUtils::printSafeJson($value);
            throw new $exceptionClass("The given value can not be coerced to a string: {$safeValue}.");
        }

        // @phpstan-ignore-next-line we have proven the value can be safely cast
        return (string) $value;
    }
}
