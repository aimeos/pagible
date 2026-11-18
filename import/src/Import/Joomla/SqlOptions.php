<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;


/**
 * Fetches the options of the custom SQL fields by running their queries on the Joomla database if they have no side effects.
 */
class SqlOptions
{
    /** Functions known to be harmless the queries of custom SQL fields can call, all others are rejected */
    protected const ALLOWED_FUNCTIONS = ['abs', 'avg', 'cast', 'ceil', 'ceiling', 'char_length', 'character_length', 'coalesce', 'concat',
        'concat_ws', 'convert', 'count', 'date', 'date_format', 'day', 'field', 'floor', 'from_unixtime', 'greatest', 'group_concat', 'hour',
        'if', 'ifnull', 'instr', 'json_extract', 'json_unquote', 'lcase', 'least', 'left', 'length', 'locate', 'lower', 'lpad', 'ltrim', 'max',
        'min', 'minute', 'mod', 'month', 'nullif', 'position', 'replace', 'reverse', 'right', 'round', 'rpad', 'rtrim', 'substr', 'substring',
        'substring_index', 'sum', 'trim', 'ucase', 'upper', 'year'];

    /** Keywords which can be followed by parentheses in the queries of custom SQL fields like "IN (1, 2)" */
    protected const PARENTHESIZED_KEYWORDS = ['and', 'as', 'between', 'distinct', 'else', 'from', 'having', 'in', 'is', 'join', 'like',
        'not', 'on', 'or', 'select', 'then', 'using', 'when', 'where', 'xor'];

    /** Keywords of statements with side effects or locks the queries of custom SQL fields must not contain */
    protected const SQL_KEYWORDS = ['into', 'outfile', 'dumpfile', 'for', 'lock', 'procedure', 'handler'];

    /** Replaces the quoted identifiers, a name which isn't an allowed function, so quoted names followed by parentheses are rejected */
    protected const QUOTED_IDENTIFIER = '_quoted_';


    /**
     * Initializes the object.
     *
     * @param Connection $db Connection to the Joomla database
     * @param Schema $schema Replaces the table prefix in the queries
     * @param \Closure(string): void $warn Shows a warning if a query is rejected or fails
     */
    public function __construct( protected Connection $db, protected Schema $schema, protected \Closure $warn )
    {
    }


    /**
     * Fetches the options of a custom SQL field by running its query on the Joomla database like Joomla does.
     *
     * The query is written by the Joomla administrators and Joomla shows its results on the site, so reading any table
     * is allowed by design. Only queries without side effects are run: a single SELECT statement or SELECTs combined
     * by UNION without subqueries, comments, variables, locks, INTO or functions which aren't known to be harmless
     * like SLEEP(). Rejected and failing queries return no options and are shown as warning.
     *
     * @param string $sql Query of the field returning the "value" and "text" columns, "#__" is replaced by the table prefix
     * @param string $field ID and title of the field shown in the warnings like "[3] Color", none if empty
     * @return array<string, string> Option texts by value in the order returned by the query
     */
    public function fetch( string $sql, string $field = '' ): array
    {
        $sql = $this->schema->sql( rtrim( trim( $sql ), "; \t\n\r" ) );
        $name = $field !== '' ? 'custom SQL field ' . $field : 'a custom SQL field';

        if( !self::readOnly( $sql ) )
        {
            ( $this->warn )( '  Ignored the options of ' . $name . ' because only single SELECT queries without side effects are run: ' . $sql );
            return [];
        }

        try {
            $rows = $this->db->select( $sql );
        } catch( QueryException $e ) {
            // Joomla shows the field without options too
            ( $this->warn )( '  Ignored the options of ' . $name . ' because its query failed: ' . $e->getMessage() );
            return [];
        }

        $options = [];

        foreach( $rows as $row )
        {
            if( is_object( $row ) && isset( $row->value ) ) {
                $options[(string) $row->value] ??= (string) ( $row->text ?? $row->value );
            }
        }

        return $options;
    }


    /**
     * Tests if the query of a custom SQL field is a SELECT query without side effects.
     *
     * The quoted strings and identifiers are removed first, so their contents can't hide or fake keywords, see
     * stripQuoted(). Backslashes are rejected because they escape quotes depending on the SQL mode of the database.
     *
     * @param string $sql Query with the table prefix
     */
    protected static function readOnly( string $sql ): bool
    {
        if( str_contains( $sql, '\\' ) ) {
            return false;
        }

        $code = self::stripQuoted( $sql );
        return !self::hasForbiddenTokens( $code ) && self::callsOnlyAllowedFunctions( $code );
    }


    /**
     * Returns the lower case query without the contents of its quoted strings and identifiers.
     *
     * Quoted strings including doubled quotes are replaced by empty strings and quoted identifiers by a name which
     * isn't an allowed function, so calls of quoted function names like `sleep`(10) are rejected. Unbalanced quotes remain.
     *
     * @param string $sql Query with the table prefix
     * @return string Lower case query like "select name as value from jos_users where name = ''"
     */
    protected static function stripQuoted( string $sql ): string
    {
        return strtolower( (string) preg_replace_callback( '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"|`(?:[^`]|``)*`/s',
            fn( array $m ) => $m[0][0] === "'" ? "''" : self::QUOTED_IDENTIFIER, $sql ) );
    }


    /**
     * Tests if the query isn't a single SELECT statement or contains tokens with side effects.
     *
     * Rejected are other statements, several statements, comments, variables, unbalanced quotes, subqueries,
     * SELECTs which aren't combined by UNION and the keywords of SQL_KEYWORDS like INTO or FOR UPDATE.
     *
     * @param string $code Lower case query without quoted contents, see stripQuoted()
     */
    protected static function hasForbiddenTokens( string $code ): bool
    {
        // statements, comments, variables and unbalanced quotes
        if( !preg_match( '/^select\s/', $code ) || preg_match( '/;|--|#|\/\*|@|["`\']/', str_replace( "''", '', $code ) ) ) {
            return true;
        }

        // subqueries, additional SELECTs are only allowed after UNION
        if( preg_match( '/\(\s*select\b/', $code ) || preg_match_all( '/\bselect\b/', $code ) !== 1 + preg_match_all( '/\bunion\b/', $code ) ) {
            return true;
        }

        return (bool) preg_match( '/\b(' . implode( '|', self::SQL_KEYWORDS ) . ')\b/', $code );
    }


    /**
     * Tests if all names followed by parentheses are allowed functions or keywords.
     *
     * @param string $code Lower case query without quoted contents, see stripQuoted()
     */
    protected static function callsOnlyAllowedFunctions( string $code ): bool
    {
        preg_match_all( '/\b([a-z_][a-z0-9_$]*)\s*\(/', $code, $matches );
        return array_diff( $matches[1], self::ALLOWED_FUNCTIONS, self::PARENTHESIZED_KEYWORDS ) === [];
    }
}
