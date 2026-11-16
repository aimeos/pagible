<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Joomla view levels and if guests can see their articles, categories, menu items and extensions.
 */
class JoomlaLevels
{
    /** Joomla view level guests can always see */
    public const PUBLIC_LEVEL = 1;

    /** Joomla guest user group used if the users component doesn't configure one or it doesn't exist */
    protected const GUEST_GROUP = 1;

    /**
     * @param array<int, array{title: string, public: bool}> $levels Titles and visibility by view level ID
     */
    public function __construct( protected array $levels = [] )
    {
    }


    /**
     * Creates the view levels from the database rows.
     *
     * The "Public" level 1 and the levels including the guest group or one of its parent groups are public.
     *
     * @param iterable<\stdClass> $rows View levels with the "id", "title" and "rules" columns
     * @param array<int, int>|null $parents Parent IDs by user group ID or NULL if the user groups are unknown
     * @param int|null $guest Guest user group configured in the users component or NULL if none is configured
     */
    public static function read( iterable $rows, ?array $parents = null, ?int $guest = null ): self
    {
        $guests = self::guestGroups( $parents, $guest );
        $levels = [];

        foreach( $rows as $row )
        {
            $id = (int) $row->id;
            // non-numeric group IDs can't match a user group, so they are skipped
            $groups = array_map( 'intval', array_filter( JoomlaValue::decode( $row->rules ?? null ), 'is_numeric' ) );
            $levels[$id] = ['title' => JoomlaRow::title( $row ), 'public' => array_intersect( $groups, $guests ) !== []];
        }

        return new self( $levels );
    }


    /**
     * Tests if guests can see the rows of the view level.
     *
     * The "Public" level 1 is public even without view levels in the database, unknown levels aren't.
     */
    public function isPublic( int $id ): bool
    {
        return $id === self::PUBLIC_LEVEL || !empty( $this->levels[$id]['public'] );
    }


    /**
     * Returns the title of the view level.
     *
     * @return string Title of the view level or its ID if it's unknown
     */
    public function title( int $id ): string
    {
        return $this->levels[$id]['title'] ?? (string) $id;
    }


    /**
     * Returns the IDs of the user groups of visitors who aren't logged in.
     *
     * These are the guest group and its parent groups like Joomla uses them.
     *
     * @param array<int, int>|null $parents Parent IDs by user group ID or NULL if the user groups are unknown
     * @param int|null $guest Configured guest user group or NULL if none is configured
     * @return array<int, int> User group IDs
     */
    protected static function guestGroups( ?array $parents, ?int $guest ): array
    {
        // like Access::getGroupsByUser(), which falls back to the public root group if the query of the guest group
        // and its parents returns no rows because no guest group is configured or it doesn't exist
        if( $guest === null || ( $parents !== null && !isset( $parents[$guest] ) ) ) {
            $guest = self::GUEST_GROUP;
        }

        $groups = [$guest];
        $parent = $parents[$guest] ?? 0;

        // groups which are their own parents don't loop
        while( $parent > 0 && !in_array( $parent, $groups, true ) )
        {
            $groups[] = $parent;
            $parent = $parents[$parent] ?? 0;
        }

        return $groups;
    }
}
