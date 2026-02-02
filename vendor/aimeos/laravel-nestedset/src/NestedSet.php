<?php

namespace Aimeos\Nestedset;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NestedSet
{
    /**
     * The name of default lft column.
     */
    const LFT = '_lft';

    /**
     * The name of default rgt column.
     */
    const RGT = '_rgt';

    /**
     * The name of default parent id column.
     */
    const PARENT_ID = 'parent_id';

    /**
     * The name of default depth column.
     */
    const DEPTH = 'depth';

    /**
     * Insert direction.
     */
    const BEFORE = 1;

    /**
     * Insert direction.
     */
    const AFTER = 2;

    /**
     * Add default nested set columns to the table. Also create an index.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $idColumn
     * @param string $type
     */
    public static function columns(Blueprint $table, string $idColumn = 'id', string $type = 'unsignedInteger'): void
    {
        $table->unsignedInteger(self::LFT)->default(0);
        $table->unsignedInteger(self::RGT)->default(0);

        $table->{$type}(self::PARENT_ID)->nullable()->index();

        $table->index(static::getDefaultColumns());
    }

    /**
     * Add additional nested set columns to the table.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $idColumn
     */
    public static function columnsDepth(Blueprint $table, string $idColumn = 'id'): void
    {
        $table->smallInteger(self::DEPTH)->default(0);

        $name = $table->getTable() . '_' . self::PARENT_ID . '_foreign';

        $table->foreign(self::PARENT_ID, $name)
            ->references($idColumn)
            ->on($table->getTable())
            ->onDelete('cascade')
            ->onUpdate('cascade');
    }

    /**
     * Drop NestedSet columns.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     */
    public static function dropColumns(Blueprint $table): void
    {
        $columns = static::getDefaultColumns();

        $table->dropIndex($columns);
        $table->dropColumn($columns);
    }

    /**
     * Drop additional NestedSet columns.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     */
    public static function dropColumnsDepth(Blueprint $table): void
    {
        $table->dropForeign(self::PARENT_ID);
        $table->dropColumn(self::DEPTH);
    }

    /**
     * Get a list of default columns.
     *
     * @return array
     */
    public static function getDefaultColumns(): array
    {
        return [ static::LFT, static::RGT, static::PARENT_ID ];
    }

    /**
     * Replaces instanceof calls for this trait.
     *
     * @param mixed $node
     *
     * @return bool
     */
    public static function isNode($node): bool
    {
        if(!is_object($node)) {
            return false;
        }

        if(array_key_exists(NodeTrait::class, class_uses($node))) {
            return true;
        }

        foreach(class_parents($node) as $parent) {
            if(array_key_exists(NodeTrait::class, class_uses($parent))) {
                return true;
            }
        }

        return false;
    }
}
