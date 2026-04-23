<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;


class HasChangedTest extends CoreTestAbstract
{
    public function testGetChangedAttributeDefaultNull()
    {
        $page = new Page();
        $this->assertNull( $page->changed );
    }


    public function testSetChanged()
    {
        $info = ['editor' => 'test@example.com', 'data' => ['title' => ['previous' => 'a', 'current' => 'b']]];

        $page = new Page();
        $result = $page->setChanged( $info );

        $this->assertSame( $page, $result );
        $this->assertEquals( $info, $page->changed );
    }


    public function testSetChangedOverwrite()
    {
        $page = new Page();
        $page->setChanged( ['editor' => 'first'] );
        $page->setChanged( ['editor' => 'second'] );

        $this->assertEquals( ['editor' => 'second'], $page->changed );
    }


    public function testSetChangedWithConflict()
    {
        $info = [
            'editor' => 'other@example.com',
            'data' => [
                'title' => [
                    'previous' => 'Original',
                    'current' => 'Their Change',
                    'overwritten' => 'My Change',
                ],
            ],
        ];

        $page = new Page();
        $page->setChanged( $info );

        $this->assertArrayHasKey( 'overwritten', $page->changed['data']['title'] );
        $this->assertEquals( 'My Change', $page->changed['data']['title']['overwritten'] );
    }
}
