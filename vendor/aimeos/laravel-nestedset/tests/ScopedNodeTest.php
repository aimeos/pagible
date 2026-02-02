<?php

use Aimeos\Nestedset\NestedSet;

class ScopedNodeTest extends ScopedNodeTestBase
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->menuItemData = new MenuItemData();
    }

    protected static function getTableName(): string
    {
        return 'menu_items';
    }

    protected static function getModelClass(): string
    {
        return MenuItem::class;
    }

    protected static function createTable(\Illuminate\Database\Schema\Blueprint $table): void
    {
        $table->increments('id');
        $table->unsignedInteger('menu_id');
        $table->string('title')->nullable();

        NestedSet::columns($table);
        NestedSet::columnsDepth($table);
    }
}
