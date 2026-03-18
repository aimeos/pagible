<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Policies\ElementPolicy;
use Aimeos\Cms\Policies\FilePolicy;
use Aimeos\Cms\Policies\PagePolicy;


class PolicyTest extends TestAbstract
{
    protected function tearDown(): void
    {
        Permission::$callback = null;
        Permission::$addCallback = null;
        Permission::$delCallback = null;

        parent::tearDown();
    }


    // --- ElementPolicy ---

    public function testElementAddAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:add', $user );
        $this->assertTrue( ( new ElementPolicy )->add( $user ) );
    }

    public function testElementAddUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->add( $user ) );
    }

    public function testElementDropAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:drop', $user );
        $this->assertTrue( ( new ElementPolicy )->drop( $user ) );
    }

    public function testElementDropUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->drop( $user ) );
    }

    public function testElementKeepAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:keep', $user );
        $this->assertTrue( ( new ElementPolicy )->keep( $user ) );
    }

    public function testElementKeepUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->keep( $user ) );
    }

    public function testElementPublishAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:publish', $user );
        $this->assertTrue( ( new ElementPolicy )->publish( $user ) );
    }

    public function testElementPublishUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->publish( $user ) );
    }

    public function testElementPurgeAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:purge', $user );
        $this->assertTrue( ( new ElementPolicy )->purge( $user ) );
    }

    public function testElementPurgeUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->purge( $user ) );
    }

    public function testElementSaveAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:save', $user );
        $this->assertTrue( ( new ElementPolicy )->save( $user ) );
    }

    public function testElementSaveUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->save( $user ) );
    }

    public function testElementViewAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'element:view', $user );
        $this->assertTrue( ( new ElementPolicy )->view( $user ) );
    }

    public function testElementViewUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new ElementPolicy )->view( $user ) );
    }


    // --- FilePolicy ---

    public function testFileAddAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:add', $user );
        $this->assertTrue( ( new FilePolicy )->add( $user ) );
    }

    public function testFileAddUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->add( $user ) );
    }

    public function testFileDropAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:drop', $user );
        $this->assertTrue( ( new FilePolicy )->drop( $user ) );
    }

    public function testFileDropUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->drop( $user ) );
    }

    public function testFileKeepAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:keep', $user );
        $this->assertTrue( ( new FilePolicy )->keep( $user ) );
    }

    public function testFileKeepUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->keep( $user ) );
    }

    public function testFilePublishAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:publish', $user );
        $this->assertTrue( ( new FilePolicy )->publish( $user ) );
    }

    public function testFilePublishUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->publish( $user ) );
    }

    public function testFilePurgeAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:purge', $user );
        $this->assertTrue( ( new FilePolicy )->purge( $user ) );
    }

    public function testFilePurgeUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->purge( $user ) );
    }

    public function testFileSaveAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:save', $user );
        $this->assertTrue( ( new FilePolicy )->save( $user ) );
    }

    public function testFileSaveUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->save( $user ) );
    }

    public function testFileViewAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'file:view', $user );
        $this->assertTrue( ( new FilePolicy )->view( $user ) );
    }

    public function testFileViewUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new FilePolicy )->view( $user ) );
    }


    // --- PagePolicy ---

    public function testPageAddAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:add', $user );
        $this->assertTrue( ( new PagePolicy )->add( $user ) );
    }

    public function testPageAddUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->add( $user ) );
    }

    public function testPageDropAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:drop', $user );
        $this->assertTrue( ( new PagePolicy )->drop( $user ) );
    }

    public function testPageDropUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->drop( $user ) );
    }

    public function testPageKeepAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:keep', $user );
        $this->assertTrue( ( new PagePolicy )->keep( $user ) );
    }

    public function testPageKeepUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->keep( $user ) );
    }

    public function testPageMoveAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:move', $user );
        $this->assertTrue( ( new PagePolicy )->move( $user ) );
    }

    public function testPageMoveUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->move( $user ) );
    }

    public function testPagePublishAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:publish', $user );
        $this->assertTrue( ( new PagePolicy )->publish( $user ) );
    }

    public function testPagePublishUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->publish( $user ) );
    }

    public function testPagePurgeAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:purge', $user );
        $this->assertTrue( ( new PagePolicy )->purge( $user ) );
    }

    public function testPagePurgeUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->purge( $user ) );
    }

    public function testPageSaveAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:save', $user );
        $this->assertTrue( ( new PagePolicy )->save( $user ) );
    }

    public function testPageSaveUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->save( $user ) );
    }

    public function testPageViewAuthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:view', $user );
        $this->assertTrue( ( new PagePolicy )->view( $user ) );
    }

    public function testPageViewUnauthorized()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( ( new PagePolicy )->view( $user ) );
    }
}
