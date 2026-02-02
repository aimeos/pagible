<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Auth;

use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;

interface Authorizer
{
    /**
     * Authorize the index controller action.
     *
     * @param Request $request
     * @param string $modelClass
     * @return bool|Response
     */
    public function index(Request $request, string $modelClass): bool|Response;

    /**
     * Authorize the store controller action.
     *
     * @param Request $request
     * @param string $modelClass
     * @return bool|Response
     */
    public function store(Request $request, string $modelClass): bool|Response;

    /**
     * Authorize the show controller action.
     *
     * @param Request $request
     * @param object $model
     * @return bool|Response
     */
    public function show(Request $request, object $model): bool|Response;

    /**
     * Authorize the update controller action.
     *
     * @param object $model
     * @param Request $request
     * @return bool|Response
     */
    public function update(Request $request, object $model): bool|Response;

    /**
     * Authorize the destroy controller action.
     *
     * @param Request $request
     * @param object $model
     * @return bool|Response
     */
    public function destroy(Request $request, object $model): bool|Response;

    /**
     * Authorize the show-related controller action.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool|Response
     */
    public function showRelated(Request $request, object $model, string $fieldName): bool|Response;

    /**
     * Authorize the show-relationship controller action.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool|Response
     */
    public function showRelationship(Request $request, object $model, string $fieldName): bool|Response;

    /**
     * Authorize the update-relationship controller action.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool|Response
     */
    public function updateRelationship(Request $request, object $model, string $fieldName): bool|Response;

    /**
     * Authorize the attach-relationship controller action.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool|Response
     */
    public function attachRelationship(Request $request, object $model, string $fieldName): bool|Response;

    /**
     * Authorize the detach-relationship controller action.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool|Response
     */
    public function detachRelationship(Request $request, object $model, string $fieldName): bool|Response;
}
