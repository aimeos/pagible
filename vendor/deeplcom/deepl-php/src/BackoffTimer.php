<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Internal class implementing exponential-backoff timer.
 * @private
 */
class BackoffTimer
{
    private const BACKOFF_INITIAL = 1.0;
    private const BACKOFF_MAX = 120.0;
    private const BACKOFF_JITTER = 0.23;
    private const BACKOFF_MULTIPLIER = 1.6;
    private $numRetries;
    private $backoff;
    private $deadline;

    public function __construct()
    {
        $this->numRetries = 0;
        $this->backoff = self::BACKOFF_INITIAL;
        $this->deadline = microtime(true) + $this->backoff;
    }

    public function getNumRetries(): int
    {
        return $this->numRetries;
    }

    public function getTimeUntilDeadline(): float
    {
        $now = microtime(true);
        return max($this->deadline - $now, 0.0);
    }

    public function sleepUntilDeadline()
    {
        $timeUntilDeadline = $this->getTimeUntilDeadline();
        // Note: usleep() with values larger than 1000000 (1 second) may not be supported by the operating system.
        if ($timeUntilDeadline > 1.0) {
            sleep(floor($timeUntilDeadline));
            $timeUntilDeadline = $this->getTimeUntilDeadline();
        }
        usleep(floor($timeUntilDeadline * 1e6));

        // Apply multiplier to current backoff time
        $this->backoff = min($this->backoff * self::BACKOFF_MULTIPLIER, self::BACKOFF_MAX);

        // Get deadline by applying jitter as a proportion of backoff:
        // if jitter is 0.1, then multiply backoff by random value in [0.9, 1.1]
        $now = microtime(true);
        $this->deadline = $now + $this->backoff * (1 + self::BACKOFF_JITTER * (2 * (rand() / getrandmax()) - 1));
        $this->numRetries++;
    }
}
