<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Testing;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use LaravelJsonApi\Exceptions\ExceptionParser;

/**
 * Class TestExceptionHandler
 *
 * This exception handler is intended for testing JSON API packages
 * using the `orchestra/testbench` package. It ensures that JSON
 * API exceptions are rendered as they would in a "real" application
 * that has added the ExceptionParser renderer to their
 * rendering stack.
 *
 * Usage in a testbench test case is as follows:
 *
 * ```php
 * protected function resolveApplicationExceptionHandler($app)
 * {
 *   $app->singleton(
 *      \Illuminate\Contracts\Debug\ExceptionHandler::class,
 *      \LaravelJsonApi\Testing\TestExceptionHandler::class
 *   );
 * }
 * ```
 *
 */
class TestExceptionHandler extends ExceptionHandler
{

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(ExceptionParser::renderer());
    }

}
