<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Schema;

use LaravelJsonApi\Core\Query\FieldSets;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\RelationshipPath;
use function array_key_exists;

/**
 * Class SchemaFields
 *
 * @see https://github.com/neomerx/json-api/issues/236
 * @see https://github.com/neomerx/json-api/issues/236#issuecomment-483978443
 */
final class SchemaFields
{

    /**
     * @var array
     */
    private array $fastRelationships;

    /**
     * @var array
     */
    private array $fastRelationshipLists;

    /**
     * @var array
     */
    private array $fastFields;

    /**
     * @var array
     */
    private array $fastFieldLists;

    /**
     * @param IncludePaths $paths
     * @param FieldSets $fieldSets
     */
    public function __construct(IncludePaths $paths, FieldSets $fieldSets)
    {
        $this->fastRelationships = [];
        $this->fastRelationshipLists = [];
        $this->fastFields = [];
        $this->fastFieldLists = [];

        /** @var RelationshipPath $path */
        foreach ($paths as $path) {
            foreach ($path as $key => $relationship) {
                $curPath = (0 == $key) ? '' : $path->take($key)->toString();
                $this->fastRelationships[$curPath][$relationship] = true;
                $this->fastRelationshipLists[$curPath][] = $relationship;
            }
        }

        foreach ($fieldSets as $type => $fieldList) {
            foreach ($fieldList as $field) {
                $this->fastFields[$type][$field] = true;
                $this->fastFieldLists[$type][]   = $field;
            }
        }
    }

    /**
     * @param string $currentPath
     * @param string $relationship
     * @return bool
     */
    public function isRelationshipRequested(string $currentPath, string $relationship): bool
    {
        return isset($this->fastRelationships[$currentPath][$relationship]);
    }

    /**
     * @param string $currentPath
     * @return array
     */
    public function getRequestedRelationships(string $currentPath): array
    {
        return $this->fastRelationshipLists[$currentPath] ?? [];
    }

    /**
     * @param string $type
     * @param string $field
     * @return bool
     */
    public function isFieldRequested(string $type, string $field): bool
    {
        return array_key_exists($type, $this->fastFields) === false ? true : isset($this->fastFields[$type][$field]);
    }

    /**
     * @param string $type
     * @return array|null
     */
    public function getRequestedFields(string $type): ?array
    {
        return $this->fastFieldLists[$type] ?? null;
    }
}
