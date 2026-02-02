<?php

// Copyright 2022 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Stores the count and limit for one usage type.
 */
class UsageDetail
{
    /**
     * @var int The amount used of this usage type.
     */
    public $count;

    /**
     * @var int The maximum allowable amount for this usage type.
     */
    public $limit;

    public function __construct(int $count, int $limit)
    {
        $this->count = $count;
        $this->limit = $limit;
    }

    /**
     * @return bool True if the amount used has already reached or passed the allowable amount, otherwise false.
     */
    public function limitReached(): bool
    {
        return $this->count >= $this->limit;
    }
}
