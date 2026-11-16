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
    /** Functions and keywords followed by parentheses the queries of custom SQL fields can use, all others are rejected */
    protected const SQL_FUNCTIONS = ['abs', 'and', 'as', 'avg', 'between', 'cast', 'ceil', 'ceiling', 'char_length', 'character_length',
        'coalesce', 'concat', 'concat_ws', 'convert', 'count', 'date', 'date_format', 'day', 'distinct', 'else', 'field', 'floor', 'from',
        'from_unixtime', 'greatest', 'group_concat', 'having', 'hour', 'if', 'ifnull', 'in', 'instr', 'is', 'join', 'json_extract',
        'json_unquote', 'lcase', 'least', 'left', 'length', 'like', 'locate', 'lower', 'lpad', 'ltrim', 'max', 'min', 'minute', 'mod',
        'month', 'not', 'nullif', 'on', 'or', 'position', 'replace', 'reverse', 'right', 'round', 'rpad', 'rtrim', 'select', 'substr',
        'substring', 'substring_index', 'sum', 'then', 'trim', 'ucase', 'upper', 'using', 'when', 'where', 'xor', 'year'];

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
     * @return array<string, string> Option texts by value in the order returned by the query
     */
    public function fetch( string $sql ): array
    {
        $sql = $this->schema->sql( rtrim( trim( $sql ), "; \t\n\r" ) );

        if( !self::readOnly( $sql ) )
        {
            ( $this->warn )( '  Ignored the options of a custom SQL field because only single SELECT queries without side effects are run: ' . $sql );
            return [];
        }

        try {
            $rows = $this->db->select( $sql );
        } catch( QueryException $e ) {
            // Joomla shows the field without options too
            ( $this->warn )( '  Ignored the options of a custom SQL field because its query failed: ' . $e->getMessage() );
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
     * The quoted strings and identifiers are removed first, so their contents can't hide or fake keywords. Quoted
     * identifiers followed by parentheses are rejected because they can be the names of any function.
     * Backslashes are rejected because they escape quotes depending on the SQL mode of the database.
     *
     * @param string $sql Query with the table prefix
     */
    protected static function readOnly( string $sql ): bool
    {
        if( str_contains( $sql, '\\' ) ) {
            return false;
        }

        // quoted strings including doubled quotes are replaced by empty strings and quoted identifiers by a name which isn't
        // an allowed function, so calls of quoted function names like `sleep`(10) are rejected, unbalanced quotes remain
        $code = strtolower( (string) preg_replace_callback( '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"|`(?:[^`]|``)*`/s',
            fn( array $m ) => $m[0][0] === "'" ? "''" : self::QUOTED_IDENTIFIER, $sql ) );

        // statements, comments, variables and unbalanced quotes
        if( !preg_match( '/^select\s/', $code ) || preg_match( '/;|--|#|\/\*|@|["`\']/', str_replace( "''", '', $code ) ) ) {
            return false;
        }

        // subqueries, additional SELECTs are only allowed after UNION
        if( preg_match( '/\(\s*select\b/', $code ) || preg_match_all( '/\bselect\b/', $code ) !== 1 + preg_match_all( '/\bunion\b/', $code ) ) {
            return false;
        }

        if( preg_match( '/\b(' . implode( '|', self::SQL_KEYWORDS ) . ')\b/', $code ) ) {
            return false;
        }

        preg_match_all( '/\b([a-z_][a-z0-9_$]*)\s*\(/', $code, $matches );
        return array_diff( $matches[1], self::SQL_FUNCTIONS ) === [];
    }
}
