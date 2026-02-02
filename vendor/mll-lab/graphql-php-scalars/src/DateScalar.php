<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

use function Safe\preg_match;

abstract class DateScalar extends ScalarType
{
    public function serialize($value): string
    {
        if (! $value instanceof \DateTimeInterface) {
            $value = $this->tryParsingDate($value, InvariantViolation::class);
        }

        return $value->format(static::outputFormat());
    }

    public function parseValue($value): \DateTimeInterface
    {
        return $this->tryParsingDate($value, Error::class);
    }

    public function parseLiteral($valueNode, ?array $variables = null): \DateTimeInterface
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error("Query error: Can only parse strings, got {$valueNode->kind}", $valueNode);
        }

        return $this->tryParsingDate($valueNode->value, Error::class);
    }

    /**
     * @template T of Error|InvariantViolation
     *
     * @param class-string<T> $exceptionClass
     *
     * @throws T
     */
    protected function tryParsingDate(mixed $value, string $exceptionClass): \DateTimeInterface
    {
        if (is_string($value)) {
            if (preg_match(static::regex(), $value, $matches) !== 1) {
                $regex = static::regex();
                throw new $exceptionClass("Value \"{$value}\" does not match \"{$regex}\". Make sure it's ISO 8601 compliant ");
            }

            if (! $this->validateDate($matches['date'])) {
                $safeValue = Utils::printSafeJson($value);
                throw new $exceptionClass("Given input value is not ISO 8601 compliant: {$safeValue}.");
            }

            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                throw new $exceptionClass($e->getMessage());
            }
        }

        $safeValue = Utils::printSafeJson($value);
        throw new $exceptionClass("Cannot parse non-string into date: {$safeValue}");
    }

    abstract protected static function outputFormat(): string;

    abstract protected static function regex(): string;

    private function validateDate(string $date): bool
    {
        // Verify the correct number of days for the month contained in the date-string.
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $day = (int) substr($date, 8, 2);

        switch ($month) {
            case 2: // February
                $isLeapYear = $this->isLeapYear($year);
                if ($isLeapYear && $day > 29) {
                    return false;
                }

                return $isLeapYear || $day <= 28;

            case 4: // April
            case 6: // June
            case 9: // September
            case 11: // November
                if ($day > 30) {
                    return false;
                }

                break;
        }

        return true;
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0)
            || $year % 400 === 0;
    }
}
