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
use Illuminate\Http\Response;
use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Responses\ErrorResponse;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Throwable;

class RequestExceptionHandler
{

    use Concerns\SetsHttpTitle;

    /**
     * RequestExceptionHandler constructor.
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
        if ($ex instanceof RequestExceptionInterface) {
            return new ErrorResponse(
                $this->toError($ex)
            );
        }

        return $next($ex);
    }

    /**
     * @param RequestExceptionInterface|Throwable $ex
     * @return Error
     */
    private function toError(RequestExceptionInterface $ex): Error
    {
        return Error::make()
            ->setStatus($status = Response::HTTP_BAD_REQUEST)
            ->setTitle($this->getTitle($status))
            ->setDetail($this->translator->get($ex->getMessage()));
    }
}
