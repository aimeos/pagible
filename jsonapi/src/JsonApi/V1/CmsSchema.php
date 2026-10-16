<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1;

use Aimeos\Cms\Concerns\ResolvesFiles;
use LaravelJsonApi\Eloquent\Schema;


/**
 * Base class of the CMS page schemas.
 */
abstract class CmsSchema extends Schema
{
    use ResolvesFiles;

    /**
     * Default page value if no pagination was sent by the client.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $defaultPagination = ['number' => 1];

    /**
     * The maximum depth of include paths.
     *
     * @var int
     */
    protected int $maxDepth = 1;


    /**
     * Schema constructor.
     *
     * @param \LaravelJsonApi\Contracts\Server\Server $server
     */
    public function __construct( \LaravelJsonApi\Contracts\Server\Server $server )
    {
        parent::__construct( $server );
        $this->maxDepth = config( 'cms.jsonapi.maxdepth', 1 );
    }


    /**
     * Determine if the resource is authorizable.
     *
     * @return bool
     */
    public function authorizable(): bool
    {
        return false;
    }
}
