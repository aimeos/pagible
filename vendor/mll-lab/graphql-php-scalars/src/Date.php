<?php declare(strict_types=1);

namespace MLL\GraphQLScalars;

class Date extends DateScalar
{
    public ?string $description /** @lang Markdown */
        = 'A date string with format `Y-m-d`, e.g. `2011-05-23`.';

    protected static function outputFormat(): string
    {
        return 'Y-m-d';
    }

    protected static function regex(): string
    {
        return '~^(?<date>\d{4}-(0[1-9]|1[012])-(0[1-9]|[12][\d]|3[01]))$~';
    }
}
