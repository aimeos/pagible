<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Database;


/**
 * Looks up the texts of the user, user group and SQL custom field values in the Joomla database.
 *
 * Like the other caches of the Joomla import, the lookups are fetched on first use and kept. The user groups and
 * the options of the SQL fields don't grow with the imported articles, the user names do, so the oldest ones are
 * removed if there are more than CACHE_SIZE.
 */
class FieldLookups
{
    /** Maximum number of cached user names */
    protected const CACHE_SIZE = 1000;

    /** @var array<int|string, string|null> Names of the users by user ID, NULL for unknown users */
    protected array $users = [];

    /** @var array<int|string, string>|null Titles of the user groups by user group ID, NULL until they are fetched on first use */
    protected ?array $groups = null;

    /** @var array<int|string, array<int|string, string>> Option texts by value of the SQL fields by field ID */
    protected array $sqlOptions = [];


    /**
     * Initializes the object.
     *
     * @param Reader $reader Reader for the users, user groups and SQL field options
     */
    public function __construct( protected Reader $reader )
    {
    }


    /**
     * Returns the titles of the user groups.
     *
     * @return array<int|string, string> Titles by user group ID
     */
    public function groups(): array
    {
        return $this->groups ??= $this->reader->userGroups();
    }


    /**
     * Returns the names of the users, empty strings for the ones which don't exist like Joomla.
     *
     * @param list<string> $ids User IDs
     * @return list<string> User names
     */
    public function names( array $ids ): array
    {
        $ids = array_values( array_filter( $ids, fn( $id ) => (int) $id !== 0 ) );
        $missing = array_values( array_filter( $ids, fn( $id ) => !array_key_exists( $id, $this->users ) ) );

        if( $missing )
        {
            $names = $this->reader->users( $missing );

            // unknown users are remembered too, so they aren't fetched again
            foreach( $missing as $id ) {
                $this->users[$id] = $names[$id] ?? null;
            }
        }

        // the names are returned before the limit may drop cached names of these users
        $result = array_map( fn( $id ) => $this->users[$id] ?? '', $ids );
        $this->users = Database::limit( $this->users, static::CACHE_SIZE, count( $missing ) );

        return $result;
    }


    /**
     * Returns the options of a SQL field, which are fetched once per field.
     *
     * @param int|string $id ID of the SQL field
     * @param string $query SQL query of the field selecting the "value" and "text" columns
     * @return array<int|string, string> Option texts by value
     */
    public function sqlOptions( int|string $id, string $query ): array
    {
        return $this->sqlOptions[$id] ??= $this->reader->sqlOptions()->fetch( $query );
    }
}
