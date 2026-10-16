<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Access;

use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Immutable set of callbacks connecting the access catalog to an authorization backend.
 */
final readonly class Adapter
{
    /**
     * @param (\Closure(): iterable<mixed>)|null $list Returns the access values of the catalog
     * @param (\Closure(string): void)|null $add Adds an access value
     * @param (\Closure(array<int, string>): void)|null $delete Deletes access values
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Returns the effective grants of a user
     * @param (\Closure(Authenticatable, ?array<int, string>): iterable<mixed>)|null $userAccess Reads (NULL) or replaces direct assignments
     * @param (\Closure(string): void)|null $activate Activates the tenant in the backend
     * @param (\Closure(Authenticatable): void)|null $prepare Prepares the user before resolving grants
     */
    public function __construct(
        public ?\Closure $list = null,
        public ?\Closure $add = null,
        public ?\Closure $delete = null,
        public ?\Closure $grants = null,
        public ?\Closure $userAccess = null,
        public ?\Closure $activate = null,
        public ?\Closure $prepare = null,
    ) {
    }
}
