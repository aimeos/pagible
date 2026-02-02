<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

abstract class IntRange extends ScalarType
{
    /** The minimum allowed value. */
    abstract protected static function min(): int;

    /** The maximum allowed value. */
    abstract protected static function max(): int;

    public function serialize($value)
    {
        if (is_int($value) && $this->isValueInExpectedRange($value)) {
            return $value;
        }

        $notInRange = Utils::printSafe($value);
        throw new \InvalidArgumentException("Value not in range {$this->rangeDescription()}: {$notInRange}.");
    }

    public function parseValue($value)
    {
        if (is_int($value) && $this->isValueInExpectedRange($value)) {
            return $value;
        }

        $notInRange = Utils::printSafe($value);
        throw new Error("Value not in range {$this->rangeDescription()}: {$notInRange}.");
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof IntValueNode) {
            $value = (int) $valueNode->value;
            if ($this->isValueInExpectedRange($value)) {
                return $value;
            }
        }

        $notInRange = Printer::doPrint($valueNode);
        throw new Error("Value not in range {$this->rangeDescription()}: {$notInRange}.", $valueNode);
    }

    private function isValueInExpectedRange(int $value): bool
    {
        return $value <= static::max() && $value >= static::min();
    }

    private function rangeDescription(): string
    {
        $min = static::min();
        $max = static::max();

        return "{$min}-{$max}";
    }
}
