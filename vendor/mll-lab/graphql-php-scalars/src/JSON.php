<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\ScalarType;
use Safe\Exceptions\JsonException;

class JSON extends ScalarType
{
    public ?string $description /** @lang Markdown */
        = 'Arbitrary data encoded in JavaScript Object Notation. See https://www.json.org.';

    public function serialize($value): string
    {
        return \Safe\json_encode($value);
    }

    public function parseValue($value)
    {
        return $this->decodeJSON($value);
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (! property_exists($valueNode, 'value')) {
            $withoutValue = Printer::doPrint($valueNode);
            throw new Error("Can not parse literals without a value: {$withoutValue}.");
        }

        return $this->decodeJSON($valueNode->value);
    }

    /**
     * Try to decode a user-given JSON value.
     *
     * @param mixed $value A user given JSON
     *
     * @throws Error
     *
     * @return mixed The decoded value
     */
    protected function decodeJSON(mixed $value): mixed
    {
        try {
            // @phpstan-ignore-next-line we attempt unsafe values and let it throw
            $decoded = \Safe\json_decode($value);
        } catch (JsonException $jsonException) {
            throw new Error($jsonException->getMessage());
        }

        return $decoded;
    }
}
