<?php

namespace Orchestra\Testbench\Exceptions;

use PHPUnit\Util\Filter;

use function Orchestra\Testbench\phpunit_version_compare;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class DeprecatedException extends PHPUnitErrorException
{
    /**
     * Convert exception to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $stackTrace = phpunit_version_compare('11.5', '>=')
            ? Filter::stackTraceFromThrowableAsString($this)
            : Filter::getFilteredStacktrace($this);

        return \sprintf('%s'.PHP_EOL.PHP_EOL.'%s', $this->getMessage(), $stackTrace);
    }
}
