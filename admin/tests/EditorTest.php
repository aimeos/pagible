<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


class EditorTest extends AdminTestAbstract
{
    public function testViewRegistered()
    {
        $this->assertTrue( view()->exists( 'cms::editor' ) );
    }
}
