<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Converts the dates of the Joomla database which are stored in UTC.
 */
class JoomlaDate
{
    /** Time zone of the dates in the Joomla database, also the default of the "offset" setting the dates are shown in */
    public const TIMEZONE = 'UTC';

    /** Formats of Joomla for dates without and with time in British English, DATE_FORMAT_LC4 and DATE_FORMAT_LC5 */
    public const DATE_FORMAT = 'Y-m-d';
    public const DATETIME_FORMAT = 'Y-m-d H:i';

    /**
     * @var list<string> Empty dates of Joomla, the ones of MySQL with and without time, e.g. of calendar fields, and
     *   in Joomla 3 the start of the Unix epoch on PostgreSQL and 1900-01-01 on SQL Server
     */
    protected const EMPTY_DATES = [...Database::EMPTY_DATES, '1970-01-01 00:00:00', '1900-01-01 00:00:00'];


    /**
     * Returns the first date which isn't empty, including the empty dates of Joomla 3 on PostgreSQL and SQL Server.
     *
     * @param mixed ...$dates Dates in the format "YYYY-MM-DD HH:MM:SS", values which aren't strings are empty
     * @return string|null First date or NULL if all are empty
     */
    public static function first( mixed ...$dates ): ?string
    {
        return Database::firstDate( $dates, self::EMPTY_DATES );
    }


    /**
     * Returns the timestamp of the first date which isn't empty.
     *
     * @param mixed ...$dates Dates in UTC in the format "YYYY-MM-DD HH:MM:SS", values which aren't strings are empty
     * @return int|null Timestamp or NULL if all dates are empty or the first one is invalid
     */
    public static function time( mixed ...$dates ): ?int
    {
        $date = self::first( ...$dates );
        return $date !== null ? self::parse( $date )?->getTimestamp() : null;
    }


    /**
     * Returns the date of the Joomla database.
     *
     * @param string $date Date in UTC, e.g. in the format "YYYY-MM-DD HH:MM:SS"
     * @return \DateTimeImmutable|null Date or NULL if it's invalid
     */
    public static function parse( string $date ): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable( $date, new \DateTimeZone( self::TIMEZONE ) );
        } catch( \Exception ) {
            return null;
        }
    }


    /**
     * Returns the date in the time zone, by default with time.
     *
     * @param \DateTimeImmutable|int $date Date or timestamp
     * @param string $format Format of the date, DATETIME_FORMAT or DATE_FORMAT
     * @param \DateTimeZone|null $timezone Time zone the date is shown in, NULL for UTC
     * @return string Formatted date
     */
    public static function format( \DateTimeImmutable|int $date, string $format = self::DATETIME_FORMAT, ?\DateTimeZone $timezone = null ): string
    {
        $date = is_int( $date ) ? new \DateTimeImmutable( '@' . $date ) : $date;
        return $date->setTimezone( $timezone ?? new \DateTimeZone( self::TIMEZONE ) )->format( $format );
    }
}
