<?php

use Illuminate\Database\Capsule\Manager as Capsule;

abstract class NodeTestBase extends \PHPUnit\Framework\TestCase
{
    abstract protected static function getTableName(): string;

    abstract protected static function getModelClass(): string;

    abstract protected static function createTable(\Illuminate\Database\Schema\Blueprint $table): void;

    protected array $ids = [];
    protected CategoryData $categoryData;

    public static function setUpBeforeClass(): void
    {
        $schema = Capsule::schema();
        $table = static::getTableName();

        $schema->dropIfExists($table);

        $schema->create($table, function (\Illuminate\Database\Schema\Blueprint $table) {
            static::createTable($table);
        });

        Capsule::enableQueryLog();

        date_default_timezone_set('Europe/Berlin');
    }

    public function setUp(): void
    {
        $this->ids = $this->categoryData->getIds();
        Capsule::table(static::getTableName())->insert($this->categoryData->getData());

        Capsule::flushQueryLog();

        $modelClass = static::getModelClass();
        $modelClass::resetActionsPerformed();
    }

    public function tearDown(): void
    {
        Capsule::table(static::getTableName())->delete();
    }

    protected function assertNodeReceivesValidValues($node)
    {
        $lft = $node->getLft();
        $rgt = $node->getRgt();
        $nodeInDb = $this->findCategory($node->name);

        $this->assertEquals(
            [$nodeInDb->getLft(), $nodeInDb->getRgt()],
            [$lft, $rgt],
            'Node is not synced with database after save.'
        );
    }

    protected function assertTreeNotBroken($table = null)
    {
        $table = $table ?? static::getTableName();
        $checks = array();

        $connection = Capsule::connection();

        $table = $connection->getQueryGrammar()->wrapTable($table);

        // Check if lft and rgt values are ok
        $checks[] = "from $table where _lft >= _rgt or (_rgt - _lft) % 2 = 0";

        // Check if lft and rgt values are unique
        $checks[] = "from $table c1, $table c2 where c1.id <> c2.id and " .
            "(c1._lft=c2._lft or c1._rgt=c2._rgt or c1._lft=c2._rgt or c1._rgt=c2._lft)";

        // Check if parent_id is set correctly
        $checks[] = "from $table c, $table p, $table m where c.parent_id=p.id and m.id <> p.id and m.id <> c.id and " .
            "(c._lft not between p._lft and p._rgt or c._lft between m._lft and m._rgt and m._lft between p._lft and p._rgt)";

        foreach ($checks as $i => $check) {
            $checks[$i] = 'select 1 as error ' . $check;
        }

        $sql = 'select max(error) as errors from (' . implode(' union ', $checks) . ') _';

        $actual = $connection->selectOne($sql);

        $this->assertEquals(null, $actual->errors, "The tree structure of $table is broken!");
        $actual = (array)Capsule::connection()->selectOne($sql);

        $this->assertEquals(array('errors' => null), $actual, "The tree structure of $table is broken!");
    }

    /**
     * @param $name
     *
     * @return \Category
     */
    protected function findCategory($name, $withTrashed = false)
    {
        $modelClass = static::getModelClass();
        $q = new $modelClass;

        $q = $withTrashed ? $q->withTrashed() : $q->newQuery();

        return $q->whereName($name)->first();
    }

    protected function dumpTree($items = null)
    {
        if (!$items) $items = static::getModelClass()::withTrashed()->defaultOrder()->get();

        foreach ($items as $item) {
            echo PHP_EOL . ($item->trashed() ? '-' : '+') . ' ' . $item->name . " " . $item->getKey() . ' ' . $item->getLft() . " " . $item->getRgt() . ' ' . $item->getParentId();
        }
    }

    protected function nodeValues($node)
    {
        return array($node->_lft, $node->_rgt, $node->parent_id, $node->depth);
    }

    public function testTreeNotBroken()
    {
        $this->assertTreeNotBroken();
        $this->assertFalse(static::getModelClass()::isBroken());
    }

    public function testGetsNodeData()
    {
        $data = static::getModelClass()::getNodeData($this->ids[3]);

        $this->assertEquals(['_lft' => 3, '_rgt' => 4, 'depth' => 2], $data);
    }

    public function testGetsPlainNodeData()
    {
        $data = static::getModelClass()::getPlainNodeData($this->ids[3]);

        $this->assertEquals([3, 4], $data);
    }

    public function testReceivesValidValuesWhenAppendedTo()
    {
        $model = static::getModelClass();
        $node = new $model(['name' => 'test']);
        $root = static::getModelClass()::root();

        $accepted = array($root->_rgt, $root->_rgt + 1, $root->id, $root->depth + 1);

        $root->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertEquals($accepted, $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertFalse($node->isDirty());
        $this->assertTrue($node->isDescendantOf($root));
    }

    public function testReceivesValidValuesWhenPrependedTo()
    {
        $root = static::getModelClass()::root();
        $model = static::getModelClass();
        $node = new $model(['name' => 'test']);
        $root->prependNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertEquals(array($root->_lft + 1, $root->_lft + 2, $root->id, $root->depth + 1), $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertTrue($node->isDescendantOf($root));
        $this->assertTrue($root->isAncestorOf($node));
        $this->assertTrue($node->isChildOf($root));
    }

    public function testReceivesValidValuesWhenInsertedAfter()
    {
        $target = $this->findCategory('apple');
        $model = static::getModelClass();
        $node = new $model(['name' => 'test']);
        $node->afterNode($target)->save();

        $this->assertTrue($node->hasMoved());
        $this->assertEquals(array($target->_rgt + 1, $target->_rgt + 2, $target->parent->id, $target->depth), $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertFalse($node->isDirty());
        $this->assertTrue($node->isSiblingOf($target));
    }

    public function testReceivesValidValuesWhenInsertedBefore()
    {
        $target = $this->findCategory('apple');
        $model = static::getModelClass();
        $node = new $model(['name' => 'test']);
        $node->beforeNode($target)->save();

        $this->assertTrue($node->hasMoved());
        $this->assertEquals(array($target->_lft, $target->_lft + 1, $target->parent->id, $target->depth), $this->nodeValues($node));
        $this->assertTreeNotBroken();
    }

    public function testCategoryMoveLevelUp()
    {
        $node = $this->findCategory('galaxy');
        $target = $this->findCategory('notebooks');

        $this->assertEquals(1, $target->getDepth());
        $this->assertEquals(3, $node->getDepth());

        $target->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertNodeReceivesValidValues($node);
        $this->assertTreeNotBroken();

        $this->assertEquals(1, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());
    }

    public function testCategoryMoveLevelSame()
    {
        $node = $this->findCategory('apple');
        $target = $this->findCategory('notebooks');

        $this->assertEquals(1, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());

        $target->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);

        $this->assertEquals(1, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());
    }

    public function testCategoryMoveLevelDown()
    {
        $node = $this->findCategory('apple');
        $target = $this->findCategory('samsung');

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());

        $target->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(3, $node->getDepth());
    }

    public function testCategoryMoveBeforeUp()
    {
        $node = $this->findCategory('galaxy');
        $target = $this->findCategory('apple');

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(3, $node->getDepth());

        $node->insertBeforeNode($target);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());
    }

    public function testCategoryMoveBeforeSame()
    {
        $node = $this->findCategory('apple');
        $target = $this->findCategory('samsung');

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());

        $node->insertBeforeNode($target);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);

        $this->assertEquals(2, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());
    }

    public function testCategoryMoveBeforeDown()
    {
        $node = $this->findCategory('apple');
        $target = $this->findCategory('galaxy');

        $this->assertEquals(3, $target->getDepth());
        $this->assertEquals(2, $node->getDepth());

        $node->insertBeforeNode($target);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);

        $this->assertEquals(3, $target->getDepth());
        $this->assertEquals(3, $node->getDepth());
    }

    public function testFailsToInsertIntoChild()
    {
        $this->expectException(Exception::class);

        $node = $this->findCategory('notebooks');
        $target = $node->children()->first();

        $node->afterNode($target)->save();
    }

    public function testFailsToAppendIntoItself()
    {
        $this->expectException(Exception::class);

        $node = $this->findCategory('notebooks');

        $node->appendToNode($node)->save();
    }

    public function testFailsToPrependIntoItself()
    {
        $this->expectException(Exception::class);

        $node = $this->findCategory('notebooks');

        $node->prependTo($node)->save();
    }

    public function testWithoutRootWorks()
    {
        $result = static::getModelClass()::withoutRoot()->pluck('name');

        $this->assertNotEquals('store', $result);
    }

    public function testAncestorsReturnsAncestorsWithoutNodeItself()
    {
        $node = $this->findCategory('apple');
        $path = all($node->ancestors()->pluck('name'));

        $this->assertEquals(array('store', 'notebooks'), $path);
    }

    public function testGetsAncestorsByStatic()
    {
        $path = all(static::getModelClass()::ancestorsOf($this->ids[3])->pluck('name'));

        $this->assertEquals(array('store', 'notebooks'), $path);
    }

    public function testGetsAncestorsDirect()
    {
        $path = all(static::getModelClass()::find($this->ids[8])->getAncestors()->pluck('id'));

        $this->assertEquals(array($this->ids[1], $this->ids[5], $this->ids[7]), $path);
    }

    public function testDescendants()
    {
        $node = $this->findCategory('mobile');
        $descendants = all($node->descendants()->pluck('name'));
        $expected = array('nokia', 'samsung', 'galaxy', 'sony', 'lenovo');

        $this->assertEquals($expected, $descendants);

        $descendants = all($node->getDescendants()->pluck('name'));

        $this->assertEquals(count($descendants), $node->getDescendantCount());
        $this->assertEquals($expected, $descendants);

        $descendants = all(static::getModelClass()::descendantsAndSelf($this->ids[7])->pluck('name'));
        $expected = ['samsung', 'galaxy'];

        $this->assertEquals($expected, $descendants);
    }

    public function testWithDepthWorks()
    {
        $nodes = all(static::getModelClass()::withDepth()->limit(4)->pluck('depth'));

        $this->assertEquals(array(0, 1, 2, 2), $nodes);
    }

    public function testWithDepthWithCustomKeyWorks()
    {
        $node = static::getModelClass()::whereIsRoot()->withDepth('level')->first();

        $this->assertTrue(isset($node['level']));
    }

    public function testWithDepthWorksAlongWithDefaultKeys()
    {
        $node = static::getModelClass()::withDepth()->first();

        $this->assertTrue(isset($node->name));
    }

    public function testParentIdAttributeAccessorAppendsNode()
    {
        $model = static::getModelClass();
        $node = new $model(array('name' => 'lg', 'parent_id' => $this->ids[5]));
        $node->save();

        $this->assertEquals($this->ids[5], $node->parent_id);
        $this->assertEquals($this->ids[5], $node->getParentId());

        $node->parent_id = null;
        $node->save();

        $node->refreshNode();

        $this->assertEquals(null, $node->parent_id);
        $this->assertTrue($node->isRoot());
    }

    public function testFailsToSaveNodeUntilNotInserted()
    {
        $this->expectException(Exception::class);

        $modelClass = static::getModelClass();
        $node = new $modelClass();
        $node->save();
    }

    public function testNodeIsDeletedWithDescendants()
    {
        $node = $this->findCategory('mobile');
        $node->forceDelete();

        $this->assertTreeNotBroken();

        $nodes = static::getModelClass()::whereIn('id', array($this->ids[5], $this->ids[6], $this->ids[7], $this->ids[8], $this->ids[9]))->count();
        $this->assertEquals(0, $nodes);

        $root = static::getModelClass()::root();
        $this->assertEquals(8, $root->getRgt());
    }

    public function testNodeIsSoftDeleted()
    {
        $root = static::getModelClass()::root();

        $samsung = $this->findCategory('samsung');
        $samsung->delete();

        $this->assertTreeNotBroken();

        $this->assertNull($this->findCategory('galaxy'));

        sleep(1);

        $node = $this->findCategory('mobile');
        $node->delete();

        $nodes = static::getModelClass()::whereIn('id', array($this->ids[5], $this->ids[6], $this->ids[7], $this->ids[8], $this->ids[9]))->count();
        $this->assertEquals(0, $nodes);

        $originalRgt = $root->getRgt();
        $root->refreshNode();

        $this->assertEquals($originalRgt, $root->getRgt());

        $node = $this->findCategory('mobile', true);

        $node->restore();

        $this->assertNull($this->findCategory('samsung'));
        $this->assertNotNull($this->findCategory('nokia'));
    }

    public function testSoftDeletedNodeisDeletedWhenParentIsDeleted()
    {
        $this->findCategory('samsung')->delete();

        $this->findCategory('mobile')->forceDelete();

        $this->assertTreeNotBroken();

        $this->assertNull($this->findCategory('samsung', true));
        $this->assertNull($this->findCategory('sony'));
    }

    public function testFailsToSaveNodeUntilParentIsSaved()
    {
        $this->expectException(Exception::class);

        $modelClass = static::getModelClass();
        $node = new $modelClass(array('title' => 'Node'));
        $parent = new $modelClass(array('title' => 'Parent'));

        $node->appendTo($parent)->save();
    }

    public function testSiblings()
    {
        $node = $this->findCategory('samsung');
        $siblings = all($node->siblings()->pluck('id'));
        $next = all($node->nextSiblings()->pluck('id'));
        $prev = all($node->prevSiblings()->pluck('id'));

        $this->assertEquals(array($this->ids[6], $this->ids[9], $this->ids[10]), $siblings);
        $this->assertEquals(array($this->ids[9], $this->ids[10]), $next);
        $this->assertEquals(array($this->ids[6]), $prev);

        $siblings = all($node->getSiblings()->pluck('id'));
        $next = all($node->getNextSiblings()->pluck('id'));
        $prev = all($node->getPrevSiblings()->pluck('id'));

        $this->assertEquals(array($this->ids[6], $this->ids[9], $this->ids[10]), $siblings);
        $this->assertEquals(array($this->ids[9], $this->ids[10]), $next);
        $this->assertEquals(array($this->ids[6]), $prev);

        $next = $node->getNextSibling();
        $prev = $node->getPrevSibling();

        $this->assertEquals($this->ids[9], $next->id);
        $this->assertEquals($this->ids[6], $prev->id);
    }

    public function testFetchesReversed()
    {
        $node = $this->findCategory('sony');
        $siblings = $node->prevSiblings()->reversed()->value('id');

        $this->assertEquals($this->ids[7], $siblings);
    }

    public function testToTreeBuildsWithDefaultOrder()
    {
        $tree = static::getModelClass()::whereBetween('_lft', array(8, 17))->defaultOrder()->get()->toTree();

        $this->assertEquals(1, count($tree));

        $root = $tree->first();
        $this->assertEquals('mobile', $root->name);
        $this->assertEquals(4, count($root->children));
    }

    public function testToTreeBuildsWithCustomOrder()
    {
        $tree = static::getModelClass()::whereBetween('_lft', array(8, 17))
            ->orderBy('title')
            ->get()
            ->toTree();

        $this->assertEquals(1, count($tree));

        $root = $tree->first();
        $this->assertEquals('mobile', $root->name);
        $this->assertEquals(4, count($root->children));
        $this->assertEquals($root, $root->children->first()->parent);
    }

    public function testToTreeWithSpecifiedRoot()
    {
        $node = $this->findCategory('mobile');
        $nodes = static::getModelClass()::whereBetween('_lft', array(8, 17))->get();

        $tree1 = \Aimeos\Nestedset\Collection::make($nodes)->toTree($this->ids[5]);
        $tree2 = \Aimeos\Nestedset\Collection::make($nodes)->toTree($node);

        $this->assertEquals(4, $tree1->count());
        $this->assertEquals(4, $tree2->count());
    }

    public function testToTreeBuildsWithDefaultOrderAndMultipleRootNodes()
    {
        $tree = static::getModelClass()::withoutRoot()->get()->toTree();

        $this->assertEquals(2, count($tree));
    }

    public function testToTreeBuildsWithRootItemIdProvided()
    {
        $tree = static::getModelClass()::whereBetween('_lft', array(8, 17))->get()->toTree($this->ids[5]);

        $this->assertEquals(4, count($tree));

        $root = $tree[1];
        $this->assertEquals('samsung', $root->name);
        $this->assertEquals(1, count($root->children));
    }

    public function testRetrievesNextNode()
    {
        $node = $this->findCategory('apple');
        $next = $node->nextNodes()->first();

        $this->assertEquals('lenovo', $next->name);
    }

    public function testRetrievesPrevNode()
    {
        $node = $this->findCategory('apple');
        $next = $node->getPrevNode();

        $this->assertEquals('notebooks', $next->name);
    }

    public function testMultipleAppendageWorks()
    {
        $parent = $this->findCategory('mobile');

        $model = static::getModelClass();
        $child = new $model(['name' => 'test']);

        $parent->appendNode($child);

        $model = static::getModelClass();
        $child->appendNode(new $model(['name' => 'sub']));

        $parent->appendNode(new $model(['name' => 'test2']));

        $this->assertTreeNotBroken();
    }

    public function testDefaultCategoryIsSavedAsRoot()
    {
        $model = static::getModelClass();
        $node = new $model(['name' => 'test']);
        $node->save();

        $this->assertEquals(23, $node->_lft);
        $this->assertTreeNotBroken();

        $this->assertTrue($node->isRoot());
    }

    public function testExistingCategorySavedAsRoot()
    {
        $node = $this->findCategory('apple');
        $node->saveAsRoot();

        $this->assertTreeNotBroken();
        $this->assertTrue($node->isRoot());
    }

    public function testNodeMovesDownSeveralPositions()
    {
        $node = $this->findCategory('nokia');

        $this->assertTrue($node->down(2));

        $this->assertEquals($node->_lft, 15);
    }

    public function testNodeMovesUpSeveralPositions()
    {
        $node = $this->findCategory('sony');

        $this->assertTrue($node->up(2));

        $this->assertEquals($node->_lft, 9);
    }

    public function testCountsTreeErrors()
    {
        $errors = static::getModelClass()::countErrors();

        $this->assertEquals(['oddness' => 0,
            'duplicates' => 0,
            'wrong_parent' => 0,
            'missing_parent' => 0], $errors);

        static::getModelClass()::where('id', '=', $this->ids[5])->update(['_lft' => 14]);
        static::getModelClass()::where('id', '=', $this->ids[8])->update(['parent_id' => $this->ids[2]]);
        static::getModelClass()::where('id', '=', $this->ids[11])->update(['_lft' => 20]);
        static::getModelClass()::where('id', '=', $this->ids[4])->update(['parent_id' => $this->ids[24]]);

        $errors = static::getModelClass()::countErrors();

        $this->assertEquals(1, $errors['oddness']);
        $this->assertEquals(2, $errors['duplicates']);
        $this->assertEquals(1, $errors['missing_parent']);
    }

    public function testCreatesNode()
    {
        $node = static::getModelClass()::create(['name' => 'test']);

        $this->assertEquals(23, $node->getLft());
    }

    public function testCreatesViaRelationship()
    {
        $node = $this->findCategory('apple');

        $child = $node->children()->create(['name' => 'test']);

        $this->assertTreeNotBroken();
    }

    public function testCreatesTree()
    {
        $node = static::getModelClass()::create(
            [
                'name' => 'test',
                'children' =>
                    [
                        ['name' => 'test2'],
                        ['name' => 'test3'],
                    ],
            ]);

        $this->assertTreeNotBroken();

        $this->assertTrue(isset($node->children));

        $node = $this->findCategory('test');

        $this->assertCount(2, $node->children);
        $this->assertEquals('test2', $node->children[0]->name);
    }

    public function testDescendantsOfNonExistingNode()
    {
        $modelClass = static::getModelClass();
        $node = new $modelClass();

        $this->assertTrue($node->getDescendants()->isEmpty());
    }

    public function testWhereDescendantsOf()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        static::getModelClass()::whereDescendantOf($this->ids[124])->get();
    }

    public function testAncestorsByNode()
    {
        $category = $this->findCategory('apple');
        $ancestors = all(static::getModelClass()::whereAncestorOf($category)->pluck('id'));

        $this->assertEquals([$this->ids[1], $this->ids[2]], $ancestors);
    }

    public function testDescendantsByNode()
    {
        $category = $this->findCategory('notebooks');
        $res = all(static::getModelClass()::whereDescendantOf($category)->pluck('id'));

        $this->assertEquals([$this->ids[3], $this->ids[4]], $res);
    }

    public function testMultipleDeletionsDoNotBrakeTree()
    {
        $category = $this->findCategory('mobile');

        foreach ($category->children()->take(2)->get() as $child) {
            $child->forceDelete();
        }

        $this->assertTreeNotBroken();
    }

    public function testTreeIsFixed()
    {
        static::getModelClass()::where('id', '=', $this->ids[5])->update(['_lft' => 14]);
        static::getModelClass()::where('id', '=', $this->ids[8])->update(['parent_id' => $this->ids[2]]);
        static::getModelClass()::where('id', '=', $this->ids[11])->update(['_lft' => 20]);
        static::getModelClass()::where('id', '=', $this->ids[2])->update(['parent_id' => $this->ids[24]]);

        $fixed = static::getModelClass()::fixTree();

        $this->assertTrue($fixed > 0);
        $this->assertTreeNotBroken();

        $node = static::getModelClass()::find($this->ids[8]);

        $this->assertEquals($this->ids[2], $node->getParentId());

        $node = static::getModelClass()::find($this->ids[2]);

        $this->assertEquals(null, $node->getParentId());
    }

    public function testSubtreeIsFixed()
    {
        static::getModelClass()::where('id', '=', $this->ids[8])->update(['_lft' => 11]);

        $fixed = static::getModelClass()::fixSubtree(static::getModelClass()::find($this->ids[5]));
        $this->assertEquals(1, $fixed);
        $this->assertTreeNotBroken();
        $this->assertEquals(12, static::getModelClass()::find($this->ids[8])->getLft());
    }

    public function testParentIdDirtiness()
    {
        $node = $this->findCategory('apple');
        $node->parent_id = $this->ids[5];

        $this->assertTrue($node->isDirty('parent_id'));

        $node = $this->findCategory('apple');
        $node->parent_id = null;

        $this->assertTrue($node->isDirty('parent_id'));
    }

    public function testIsDirtyMovement()
    {
        $node = $this->findCategory('apple');
        $otherNode = $this->findCategory('samsung');

        $this->assertFalse($node->isDirty());

        $node->afterNode($otherNode);

        $this->assertTrue($node->isDirty());

        $node = $this->findCategory('apple');
        $otherNode = $this->findCategory('samsung');

        $this->assertFalse($node->isDirty());

        $node->appendToNode($otherNode);

        $this->assertTrue($node->isDirty());
    }

    public function testRootNodesMoving()
    {
        $node = $this->findCategory('store');
        $node->down();

        $this->assertEquals(3, $node->getLft());
    }

    public function testDescendantsRelation()
    {
        $node = $this->findCategory('notebooks');
        $result = $node->descendants;

        $this->assertEquals(2, $result->count());
        $this->assertEquals('apple', $result->first()->name);
    }

    public function testDescendantsEagerlyLoaded()
    {
        $nodes = static::getModelClass()::whereIn('id', [$this->ids[2], $this->ids[5]])->get();

        $nodes->load('descendants');

        $this->assertEquals(2, $nodes->count());
        $this->assertTrue($nodes->first()->relationLoaded('descendants'));
    }

    public function testDescendantsRelationQuery()
    {
        $nodes = static::getModelClass()::has('descendants')->whereIn('id', [$this->ids[2], $this->ids[3]])->get();

        $this->assertEquals(1, $nodes->count());
        $this->assertEquals($this->ids[2], $nodes->first()->getKey());

        $nodes = static::getModelClass()::has('descendants', '>', 2)->get();

        $this->assertEquals(2, $nodes->count());
        $this->assertEquals($this->ids[1], $nodes[0]->getKey());
        $this->assertEquals($this->ids[5], $nodes[1]->getKey());
    }

    public function testParentRelationQuery()
    {
        $nodes = static::getModelClass()::has('parent')->whereIn('id', [$this->ids[1], $this->ids[2]]);

        $this->assertEquals(1, $nodes->count());
        $this->assertEquals($this->ids[2], $nodes->first()->getKey());
    }

    public function testRebuildTree()
    {
        $fixed = static::getModelClass()::rebuildTree([
            [
                'id' => $this->ids[1],
                'children' => [
                    ['id' => $this->ids[10]],
                    ['id' => $this->ids[3], 'name' => 'apple v2', 'children' => [['name' => 'new node']]],
                    ['id' => $this->ids[2]],

                ]
            ]
        ]);

        $this->assertTrue($fixed > 0);
        $this->assertTreeNotBroken();

        $node = static::getModelClass()::find($this->ids[3]);

        $this->assertEquals($this->ids[1], $node->getParentId());
        $this->assertEquals('apple v2', $node->name);
        $this->assertEquals(4, $node->getLft());

        $node = $this->findCategory('new node');

        $this->assertNotNull($node);
        $this->assertEquals($this->ids[3], $node->getParentId());
    }

    public function testRebuildSubtree()
    {
        $fixed = static::getModelClass()::rebuildSubtree(static::getModelClass()::find($this->ids[7]), [
            ['name' => 'new node'],
            ['id' => strval($this->ids[8])],
        ]);

        $this->assertTrue($fixed > 0);
        $this->assertTreeNotBroken();

        $node = $this->findCategory('new node');

        $this->assertNotNull($node);
        $this->assertEquals($node->getLft(), 12);
    }

    public function testRebuildTreeWithDeletion()
    {
        static::getModelClass()::rebuildTree([['name' => 'all deleted']], true);

        $this->assertTreeNotBroken();

        $nodes = static::getModelClass()::get();

        $this->assertEquals(1, $nodes->count());
        $this->assertEquals('all deleted', $nodes->first()->name);

        $nodes = static::getModelClass()::withTrashed()->get();

        $this->assertTrue($nodes->count() > 1);
    }

    public function testRebuildFailsWithInvalidPK()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        static::getModelClass()::rebuildTree([['id' => $this->ids[24]]]);
    }

    public function testFlatTree()
    {
        $node = $this->findCategory('mobile');
        $tree = $node->descendants()->orderBy('name')->get()->toFlatTree();

        $this->assertCount(5, $tree);
        $this->assertEquals('samsung', $tree[2]->name);
        $this->assertEquals('galaxy', $tree[3]->name);
    }

    public function testWhereIsLeaf()
    {
        $categories = static::getModelClass()::leaves();

        $this->assertEquals(7, $categories->count());
        $this->assertEquals('apple', $categories->first()->name);
        $this->assertTrue($categories->first()->isLeaf());

        $category = static::getModelClass()::whereIsRoot()->first();

        $this->assertFalse($category->isLeaf());
    }

    public function testEagerLoadAncestors()
    {
        $queryLogCount = count(Capsule::connection()->getQueryLog());
        $categories = static::getModelClass()::with('ancestors')->orderBy('name')->get();

        $this->assertEquals($queryLogCount + 2, count(Capsule::connection()->getQueryLog()));


        $expectedShape = [
            'apple (' . $this->ids[3] . ')}' => 'store (' . $this->ids[1] . ') > notebooks (' . $this->ids[2] . ')',
            'galaxy (' . $this->ids[8] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ') > samsung (' . $this->ids[7] . ')',
            'lenovo (' . $this->ids[4] . ')}' => 'store (' . $this->ids[1] . ') > notebooks (' . $this->ids[2] . ')',
            'lenovo (' . $this->ids[10] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'mobile (' . $this->ids[5] . ')}' => 'store (' . $this->ids[1] . ')',
            'nokia (' . $this->ids[6] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'notebooks (' . $this->ids[2] . ')}' => 'store (' . $this->ids[1] . ')',
            'samsung (' . $this->ids[7] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'sony (' . $this->ids[9] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'store (' . $this->ids[1] . ')}' => '',
            'store_2 (' . $this->ids[11] . ')}' => ''
        ];

        $output = [];

        foreach ($categories as $category) {
            $output["{$category->name} ({$category->id})}"] = $category->ancestors->count()
                ? implode(' > ', $category->ancestors->map(function ($cat) {
                    return "{$cat->name} ({$cat->id})";
                })->toArray())
                : '';
        }

        $this->assertEquals($expectedShape, $output);
    }

    public function testLazyLoadAncestors()
    {
        $queryLogCount = count(Capsule::connection()->getQueryLog());
        $categories = static::getModelClass()::orderBy('name')->get();

        $this->assertEquals($queryLogCount + 1, count(Capsule::connection()->getQueryLog()));

        $expectedShape = [
            'apple (' . $this->ids[3] . ')}' => 'store (' . $this->ids[1] . ') > notebooks (' . $this->ids[2] . ')',
            'galaxy (' . $this->ids[8] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ') > samsung (' . $this->ids[7] . ')',
            'lenovo (' . $this->ids[4] . ')}' => 'store (' . $this->ids[1] . ') > notebooks (' . $this->ids[2] . ')',
            'lenovo (' . $this->ids[10] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'mobile (' . $this->ids[5] . ')}' => 'store (' . $this->ids[1] . ')',
            'nokia (' . $this->ids[6] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'notebooks (' . $this->ids[2] . ')}' => 'store (' . $this->ids[1] . ')',
            'samsung (' . $this->ids[7] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'sony (' . $this->ids[9] . ')}' => 'store (' . $this->ids[1] . ') > mobile (' . $this->ids[5] . ')',
            'store (' . $this->ids[1] . ')}' => '',
            'store_2 (' . $this->ids[11] . ')}' => ''
        ];

        $output = [];

        foreach ($categories as $category) {
            $output["{$category->name} ({$category->id})}"] = $category->ancestors->count()
                ? implode(' > ', $category->ancestors->map(function ($cat) {
                    return "{$cat->name} ({$cat->id})";
                })->toArray())
                : '';
        }

        // assert that there is number of original query + 1 + number of rows to fulfill the relation
        $this->assertEquals($queryLogCount + 12, count(Capsule::connection()->getQueryLog()));

        $this->assertEquals($expectedShape, $output);
    }

    public function testWhereHasCountQueryForAncestors()
    {
        $categories = all(static::getModelClass()::has('ancestors', '>', 2)->pluck('name'));

        $this->assertEquals(['galaxy'], $categories);

        $categories = all(static::getModelClass()::whereHas('ancestors', function ($query) {
            $query->where('id', $this->ids[5]);
        })->pluck('name'));

        $this->assertEquals(['nokia', 'samsung', 'galaxy', 'sony', 'lenovo'], $categories);
    }

    public function testReplication()
    {
        $category = $this->findCategory('nokia');
        $category = $category->replicate();
        $category->save();
        $category->refreshNode();

        $this->assertNull($category->getParentId());

        $category = $this->findCategory('nokia');
        $category = $category->replicate();
        $category->parent_id = $this->ids[1];
        $category->save();

        $category->refreshNode();

        $this->assertEquals($this->ids[1], $category->getParentId());
    }
}

function all($items)
{
    return is_array($items) ? $items : $items->all();
}
