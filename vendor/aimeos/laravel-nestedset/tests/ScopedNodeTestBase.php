<?php

use Illuminate\Database\Capsule\Manager as Capsule;

abstract class ScopedNodeTestBase extends \PHPUnit\Framework\TestCase
{
    abstract protected static function getTableName(): string;

    abstract protected static function getModelClass(): string;

    abstract protected static function createTable(\Illuminate\Database\Schema\Blueprint $table): void;

    protected array $ids = [];
    protected MenuItemData $menuItemData;

    public static function setUpBeforeClass(): void
    {
        $schema = Capsule::schema();
        $table = static::getTableName();

        $schema->dropIfExists($table);

        Capsule::disableQueryLog();

        $schema->create($table, function (\Illuminate\Database\Schema\Blueprint $table) {
            $testClass = get_called_class();
            (new $testClass('dummy'))->createTable($table);
        });

        Capsule::enableQueryLog();
    }

    public function setUp(): void
    {
        $this->ids = $this->menuItemData->getIds();
        Capsule::table(static::getTableName())->insert($this->menuItemData->getData());

        Capsule::flushQueryLog();

        $modelClass = static::getModelClass();
        $modelClass::resetActionsPerformed();

        date_default_timezone_set('America/Denver');
    }

    public function tearDown(): void
    {
        Capsule::table(static::getTableName())->truncate();
    }

    protected function assertOtherScopeNotAffected()
    {
        $node = static::getModelClass()::find($this->ids[3]);

        $this->assertEquals(1, $node->getLft());
    }

    protected function assertTreeNotBroken($menuId)
    {
        $this->assertFalse(static::getModelClass()::scoped(['menu_id' => $menuId])->isBroken());
    }

    public function testNotBroken()
    {
        $this->assertTreeNotBroken(1);
        $this->assertTreeNotBroken(2);
    }

    public function testMovingNodeNotAffectingOtherMenu()
    {
        $node = static::getModelClass()::where('menu_id', '=', 1)->first();

        $node->down();

        $node = static::getModelClass()::where('menu_id', '=', 2)->first();

        $this->assertEquals(1, $node->getLft());
    }

    public function testScoped()
    {
        $node = static::getModelClass()::scoped(['menu_id' => 2])->first();

        $this->assertEquals($this->ids[3], $node->getKey());
    }

    public function testSiblings()
    {
        $node = static::getModelClass()::find($this->ids[1]);

        $result = $node->getSiblings();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($this->ids[2], $result->first()->getKey());

        $result = $node->getNextSiblings();

        $this->assertEquals($this->ids[2], $result->first()->getKey());

        $node = static::getModelClass()::find($this->ids[2]);

        $result = $node->getPrevSiblings();

        $this->assertEquals($this->ids[1], $result->first()->getKey());
    }

    public function testDescendants()
    {
        $node = static::getModelClass()::find($this->ids[2]);

        $result = $node->getDescendants();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($this->ids[5], $result->first()->getKey());

        $node = static::getModelClass()::scoped(['menu_id' => 1])->with('descendants')->find($this->ids[2]);

        $result = $node->descendants;

        $this->assertEquals(1, $result->count());
        $this->assertEquals($this->ids[5], $result->first()->getKey());
    }

    public function testAncestors()
    {
        $node = static::getModelClass()::find($this->ids[5]);

        $result = $node->getAncestors();

        $this->assertEquals(1, $result->count());
        $this->assertEquals($this->ids[2], $result->first()->getKey());

        $node = static::getModelClass()::scoped(['menu_id' => 1])->with('ancestors')->find($this->ids[5]);

        $result = $node->ancestors;

        $this->assertEquals(1, $result->count());
        $this->assertEquals($this->ids[2], $result->first()->getKey());
    }

    public function testDepth()
    {
        $node = static::getModelClass()::scoped(['menu_id' => 1])->withDepth()->where('id', '=', $this->ids[5])->first();

        $this->assertEquals(1, $node->depth);

        $node = static::getModelClass()::find($this->ids[2]);

        $result = $node->children()->withDepth()->get();

        $this->assertEquals(1, $result->first()->depth);
    }

    public function testSaveAsRoot()
    {
        $node = static::getModelClass()::find($this->ids[5]);

        $node->saveAsRoot();

        $this->assertEquals(5, $node->getLft());
        $this->assertEquals(null, $node->parent_id);

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertion()
    {
        $node = static::getModelClass()::create(['menu_id' => 1, 'parent_id' => $this->ids[5]]);

        $this->assertEquals($this->ids[5], $node->parent_id);
        $this->assertEquals(5, $node->getLft());

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertionToParentFromOtherScope()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $node = static::getModelClass()::create(['menu_id' => 2, 'parent_id' => $this->ids[5]]);
    }

    public function testDeletion()
    {
        $node = static::getModelClass()::find($this->ids[2])->delete();

        $node = static::getModelClass()::find($this->ids[1]);

        $this->assertEquals(2, $node->getRgt());

        $this->assertOtherScopeNotAffected();
    }

    public function testMoving()
    {
        $node = static::getModelClass()::find($this->ids[1]);
        $this->assertTrue($node->down());

        $this->assertOtherScopeNotAffected();
    }

    public function testAppendingToAnotherScopeFails()
    {
        $this->expectException(LogicException::class);

        $a = static::getModelClass()::find($this->ids[1]);
        $b = static::getModelClass()::find($this->ids[3]);

        $a->appendToNode($b)->save();
    }

    public function testInsertingBeforeAnotherScopeFails()
    {
        $this->expectException(LogicException::class);

        $a = static::getModelClass()::find($this->ids[1]);
        $b = static::getModelClass()::find($this->ids[3]);

        $a->insertAfterNode($b);
    }

    public function testEagerLoadingAncestorsWithScope()
    {
        $filteredNodes = static::getModelClass()::where('title', 'menu item 3')->with(['ancestors'])->get();

        $this->assertEquals($this->ids[2], $filteredNodes->find($this->ids[5])->ancestors[0]->id);
        $this->assertEquals($this->ids[4], $filteredNodes->find($this->ids[6])->ancestors[0]->id);
    }

    public function testEagerLoadingDescendantsWithScope()
    {
        $filteredNodes = static::getModelClass()::where('title', 'menu item 2')->with(['descendants'])->get();

        $this->assertEquals($this->ids[5], $filteredNodes->find($this->ids[2])->descendants[0]->id);
        $this->assertEquals($this->ids[6], $filteredNodes->find($this->ids[4])->descendants[0]->id);
    }
}
