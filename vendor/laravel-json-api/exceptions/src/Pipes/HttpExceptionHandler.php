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
use Illuminate\Contracts\Translation\Translator;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Responses\ErrorResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class HttpExceptionHandler
{

    use Concerns\SetsHttpTitle;

    /**
     * HttpExceptionHandler constructor.
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
        if ($ex instanceof HttpExceptionInterface) {
            return ErrorResponse::error($this->toError($ex))
                ->withHeaders($ex->getHeaders());
        }

        return $next($ex);
    }

    /**
     * @param HttpExceptionInterface $ex
     * @return Error
     */
    private function toError(HttpExceptionInterface $ex): Error
    {
        return Error::make()
            ->setStatus($status = $ex->getStatusCode())
            ->setTitle($this->getTitle($status))
            ->setDetail($this->translator->get($ex->getMessage()));
    }

}
