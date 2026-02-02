<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils as GraphQLUtils;

abstract class StringScalar extends ScalarType
{
    /**
     * Instantiate an anonymous subclass that can be used in a schema.
     *
     * @param string $name the name that the scalar type will have in the schema
     * @param string|null $description a description for the type
     * @param callable(string): bool $isValid a function that returns a boolean whether a given string is valid
     */
    public static function make(string $name, ?string $description, callable $isValid): self
    {
        $concreteStringScalar = new class() extends StringScalar {
            /** @var callable(string): bool */
            public $isValid;

            protected function isValid(string $stringValue): bool
            {
                return ($this->isValid)($stringValue);
            }
        };

        $concreteStringScalar->name = $name;
        $concreteStringScalar->description = $description;
        $concreteStringScalar->isValid = $isValid;

        return $concreteStringScalar;
    }

    /** Check if the given string is valid. */
    abstract protected function isValid(string $stringValue): bool;

    public function serialize($value): string
    {
        $stringValue = Utils::coerceToString($value, InvariantViolation::class);

        if (! $this->isValid($stringValue)) {
            throw new InvariantViolation($this->invalidStringMessage($stringValue));
        }

        return $stringValue;
    }

    /** Construct an error message that occurs when an invalid string is passed. */
    public function invalidStringMessage(string $stringValue): string
    {
        $safeValue = GraphQLUtils::printSafeJson($stringValue);

        return "The given string {$safeValue} is not a valid {$this->inferName()}.";
    }

    public function parseValue($value): string
    {
        $stringValue = Utils::coerceToString($value, Error::class);

        if (! $this->isValid($stringValue)) {
            throw new Error($this->invalidStringMessage($stringValue));
        }

        return $stringValue;
    }

    public function parseLiteral($valueNode, ?array $variables = null): string
    {
        $stringValue = Utils::extractStringFromLiteral($valueNode);

        if (! $this->isValid($stringValue)) {
            throw new Error($this->invalidStringMessage($stringValue), $valueNode);
        }

        return $stringValue;
    }
}
