<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Pagination\Cursor;

use Illuminate\Pagination\Cursor as LaravelCursor;
use LaravelJsonApi\Core\Schema\IdParser;

final readonly class CursorParser
{
    /**
     * CursorParser constructor.
     *
     * @param IdParser $idParser
     * @param string $keyName
     */
    public function __construct(private IdParser $idParser, private string $keyName)
    {
    }

    /**
     * @param LaravelCursor $cursor
     * @return string
     */
    public function encode(LaravelCursor $cursor): string
    {
        try {
            $key = $cursor->parameter($this->keyName);
            $parameters = $this->withoutPrivate($cursor->toArray());
            $parameters[$this->keyName] = $this->idParser->encode($key);
            $cursor = new LaravelCursor($parameters, $cursor->pointsToNextItems());
        } catch (\UnexpectedValueException $ex) {
           // Do nothing as the cursor does not contain the key.
        }

        return $cursor->encode();
    }

    /**
     * @param Cursor $cursor
     * @return LaravelCursor|null
     */
    public function decode(Cursor $cursor): ?LaravelCursor
    {
        $decoded = LaravelCursor::fromEncoded(
            $cursor->isBefore() ? $cursor->getBefore() : $cursor->getAfter(),
        );

        if ($decoded === null) {
            return null;
        }

        $parameters = $this->withoutPrivate($decoded->toArray());

        if (isset($parameters[$this->keyName])) {
            $parameters[$this->keyName] = $this->idParser->decode(
                $parameters[$this->keyName],
            );
        }

        return new LaravelCursor($parameters, $decoded->pointsToNextItems());
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutPrivate(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
