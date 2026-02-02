<?php

namespace Aimeos\Nestedset;

use Illuminate\Database\Eloquent\Model;

class AncestorsRelation extends BaseRelation
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if ( ! static::$constraints) return;

        $this->query->whereAncestorOf($this->parent)
            ->applyNestedSetScope();
    }

    /**
     * @param Model $model
     * @param Model $related
     *
     * @return bool
     */
    protected function matches(Model $model, Model $related): bool
    {
        return $related->isAncestorOf($model);
    }

    /**
     * @param QueryBuilder $query
     * @param Model $model
     *
     * @return void
     */
    protected function addEagerConstraint(QueryBuilder $query, Model $model): void
    {
        $query->orWhereAncestorOf($model);
    }

    /**
     * @param string $hash
     * @param string $table
     * @param string $lft
     * @param string $rgt
     *
     * @return string
     */
    protected function relationExistenceCondition(string $hash, string $table, string $lft, string $rgt): string
    {
        $key = $this->getBaseQuery()->getGrammar()->wrap($this->parent->getKeyName());

        return "{$table}.{$rgt} between {$hash}.{$lft} and {$hash}.{$rgt} and $table.$key <> $hash.$key";
    }
}
