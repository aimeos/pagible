<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Contracts\Encoder;

use LaravelJsonApi\Contracts\Server\Server;

interface Factory
{
    /**
     * Build a new encoder instance for the supplied server.
     *
     * @param Server $server
     * @return Encoder
     */
    public function build(Server $server): Encoder;
}
