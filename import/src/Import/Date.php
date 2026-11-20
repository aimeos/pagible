<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Date helpers shared by the importers.
 */
class Date
{
    /** Empty date of MySQL databases, e.g. used by Joomla and WordPress */
    private const ZERO_DATE = '0000-00-00 00:00:00';

    /** Empty dates of MySQL databases with and without time, also with fractions of seconds like "0000-00-00 00:00:00.000" */
    public const EMPTY_DATES = [self::ZERO_DATE, '0000-00-00'];

    /** Length of the dates without fractions of seconds, e.g. "0000-00-00 00:00:00" */
    protected const DATE_LENGTH = 19;


    /**
     * Returns the first date which isn't empty, e.g. to fall back to the local date if the UTC one is missing.
     *
     * This is the only implementation used by all importers. Empty strings, values which aren't strings and
     * the empty dates are skipped, "0" isn't empty. The empty dates are compared without fractions of seconds.
     *
     * @param iterable<mixed> $dates Dates in the format "YYYY-MM-DD HH:MM:SS", values which aren't strings are empty
     * @param array<int, string> $empty Empty dates, by default the ones of MySQL with and without time
     * @return string|null First date or NULL if all are empty
     */
    public static function first( iterable $dates, array $empty = self::EMPTY_DATES ): ?string
    {
        foreach( $dates as $date )
        {
            if( is_string( $date ) && $date !== '' && !in_array( substr( $date, 0, self::DATE_LENGTH ), $empty, true ) ) {
                return $date;
            }
        }

        return null;
    }
}
