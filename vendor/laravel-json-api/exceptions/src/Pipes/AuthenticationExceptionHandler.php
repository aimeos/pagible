<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Exceptions\Pipes;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Translation\Translator;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Responses\ErrorResponse;
use Throwable;

class AuthenticationExceptionHandler
{

    use Concerns\SetsHttpTitle;

    /**
     * AuthenticationExceptionHandler constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Handle the exception.
     *
     * @param Throwable $ex
     * @param Closure $next
     * @return ErrorResponse
     */
    public function handle(Throwable $ex, Closure $next): ErrorResponse
    {
        if ($ex instanceof AuthenticationException) {
            return new ErrorResponse(
                $this->toError($ex)
            );
        }

        return $next($ex);
    }

    /**
     * @param AuthenticationException $ex
     * @return Error
     */
    private function toError(AuthenticationException $ex): Error
    {
        return Error::make()
            ->setStatus(401)
            ->setTitle($this->getTitle(401))
            ->setDetail($this->translator->get($ex->getMessage()));
    }
}
