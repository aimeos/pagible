<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;


/**
 * SQL fragment built from values escaped by the connection and constant column names.
 *
 * Unlike Laravel's Expression, it isn't limited to literal strings and is used for
 * conditions which must not use bindings, e.g. the page variant condition.
 *
 * @internal
 */
final class Literal implements Expression
{
    /**
     * @param string $sql Escaped SQL fragment
     */
    public function __construct( private readonly string $sql )
    {
    }


    /**
     * Returns the SQL fragment.
     *
     * @param Grammar $grammar Grammar of the connection
     * @return string SQL fragment
     */
    public function getValue( Grammar $grammar ) : string
    {
        return $this->sql;
    }
}
