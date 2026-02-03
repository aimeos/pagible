<?php

use Aimeos\Nestedset\NestedSet;

class ScopedNodeUuidTest extends ScopedNodeTestBase
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->menuItemData = new MenuItemData(true);
    }

    protected static function getTableName(): string
    {
        return 'uuid_menu_items';
    }

    protected static function getModelClass(): string
    {
        return MenuItemUuid::class;
    }

    protected static function createTable(\Illuminate\Database\Schema\Blueprint $table): void
    {
        $table->uuid('id');
        $table->unsignedInteger('menu_id');
        $table->string('title')->nullable();

        NestedSet::columns($table, 'id', 'uuid');
        NestedSet::columnsDepth($table);
    }
}
