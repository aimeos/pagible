<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PermissionChanged;
use Aimeos\Cms\Listeners\PermissionLogListener;
use Aimeos\Cms\Permission;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;


class PermissionChangedTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.watch.channel', 'cms' );
        $app['config']->set( 'cms.watch.anonymize', true );
    }


    public function testSetDispatchesPermissionChanged() : void
    {
        Event::fake( [PermissionChanged::class] );

        $user = $this->user( 'audit-set@example.com' );
        Permission::set( $user, ['page:view'] );

        Event::assertDispatched( PermissionChanged::class, fn( PermissionChanged $e ) =>
            $e->targetEmail === 'audit-set@example.com'
            && $e->targetId === (string) $user->getKey()
            && $e->assignments === ['page:view']
        );
    }


    public function testArtisanCommandDispatchesPermissionChanged() : void
    {
        Event::fake( [PermissionChanged::class] );

        $this->user( 'audit-cli@example.com' );
        $this->artisan( 'cms:user', ['email' => 'audit-cli@example.com', '--enable' => true] );

        Event::assertDispatched( PermissionChanged::class, fn( PermissionChanged $e ) =>
            $e->targetEmail === 'audit-cli@example.com'
        );
    }


    public function testListenerWritesWarningLine() : void
    {
        $logger = \Mockery::mock( \Psr\Log\LoggerInterface::class );
        $logger->shouldReceive( 'warning' )->once()->with( 'cms.user', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'permission'
            && $ctx['target'] === 'audit-log@example.com'
            && $ctx['assignments'] === ['page:view']
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new PermissionLogListener )->handle( new PermissionChanged(
            actorEmail: 'admin@example.com',
            targetEmail: 'audit-log@example.com',
            targetId: '1',
            assignments: ['page:view'],
            ip: '127.0.0.1',
            tenant: 'test',
        ) );
    }


    protected function user( string $email ) : \App\Models\User
    {
        return \App\Models\User::create( [
            'name' => 'Editor',
            'email' => $email,
            'password' => 'secret',
            'cmsperms' => [],
        ] );
    }
}
