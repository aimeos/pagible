<?php

namespace Orchestra\Testbench\PHPUnit;

use Orchestra\Testbench\Concerns\HandlesAssertions;
use Orchestra\Testbench\Concerns\InteractsWithMockery;
use Throwable;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use HandlesAssertions;
    use InteractsWithMockery;

    /** {@inheritDoc} */
    #[\Override]
    protected function tearDown(): void
    {
        $this->tearDownTheTestEnvironmentUsingMockery();
    }

    /** {@inheritDoc} */
    #[\Override]
    protected function transformException(Throwable $error): Throwable
    {
        return $error;
    }
}
