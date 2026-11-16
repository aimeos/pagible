<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Major version of the Joomla site.
 */
final readonly class JoomlaVersion
{
    /** Joomla 3, the last version with the legacy router, without publishing dates of the menu items and the media field */
    public const JOOMLA_3 = 3;

    /** Joomla 4 */
    public const JOOMLA_4 = 4;


    /**
     * Initializes the object.
     *
     * @param int|null $major Major Joomla version like 3, 4 or 5 or NULL if it's unknown
     */
    public function __construct( private ?int $major = null )
    {
    }


    /**
     * Tests if the Joomla version is known and not newer than the major version.
     *
     * Unknown versions are handled like the current Joomla versions.
     *
     * @param int $major Major version like 3
     */
    public function upTo( int $major ): bool
    {
        return $this->major !== null && $this->major <= $major;
    }
}
