<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Testing;

trait MakesJsonApiRequests
{

    /**
     * Test a JSON API URI.
     *
     * @param string|null $expects
     *      the expected resource type.
     * @return TestBuilder
     */
    protected function jsonApi(?string $expects = null): TestBuilder
    {
        $tester = new TestBuilder($this);

        if ($expects) {
            $tester->expects($expects);
        }

        return $tester;
    }

}
