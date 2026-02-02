<?php

use Aimeos\Nestedset\NestedSet;

class NodeTest extends NodeTestBase
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->categoryData = new CategoryData();
    }

    protected static function getTableName(): string
    {
        return 'categories';
    }

    protected static function getModelClass(): string
    {
        return Category::class;
    }

    protected static function createTable(\Illuminate\Database\Schema\Blueprint $table): void
    {
        $table->increments('id');
        $table->string('name');
        $table->softDeletes();

        NestedSet::columns($table);
        NestedSet::columnsDepth($table);
    }
}
