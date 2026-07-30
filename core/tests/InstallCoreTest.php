<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\InstallCore;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class InstallCoreTest extends CoreTestAbstract
{
    public function testMigratesExistingDiskConfiguration() : void
    {
        $path = base_path( 'config/cms.php' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;
        $config = config( 'cms' );

        try
        {
            if( !is_dir( dirname( $path ) ) ) {
                mkdir( dirname( $path ), 0755, true );
            }

            file_put_contents( $path, "<?php\nreturn [\n    'disk' => env( 'CMS_DISK', 'media' ),\n];\n" );

            $command = new InstallCore();
            $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );
            $result = ( new \ReflectionMethod( $command, 'config' ) )->invoke( $command );
            $content = (string) file_get_contents( $path );

            $this->assertSame( 0, $result );
            $this->assertStringNotContainsString( "'disk' =>", $content );
            $this->assertStringContainsString( "'disks' =>", $content );
            $this->assertStringContainsString( "'name' => env( 'CMS_DISK', 'media' )", $content );
            $this->assertStringContainsString( "'ttl' => (int) env( 'CMS_PRIVATE_TTL', 300 )", $content );
            $this->assertSame( 'media', config( 'cms.disks.public.name' ) );
            $this->assertSame( 'local', config( 'cms.disks.private.name' ) );
            $this->assertSame( 300, config( 'cms.disks.private.ttl' ) );
        }
        finally
        {
            config( ['cms' => $config] );

            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }


    public function testRenamesExistingBroadcastLimiter() : void
    {
        $path = base_path( 'config/cms.php' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;
        $config = config( 'cms' );

        try
        {
            if( !is_dir( dirname( $path ) ) ) {
                mkdir( dirname( $path ), 0755, true );
            }

            file_put_contents( $path, "<?php\nreturn [\n"
                . "    'broadcast-middleware' => ['throttle:cms-admin'],\n"
                . "    'disks' => [],\n"
                . "];\n" );

            $command = new InstallCore();
            $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );
            $result = ( new \ReflectionMethod( $command, 'config' ) )->invoke( $command );

            $content = (string) file_get_contents( $path );

            $this->assertSame( 0, $result );
            $this->assertStringContainsString( 'throttle:cms-broadcast', $content );
            $this->assertStringNotContainsString( 'throttle:cms-admin', $content );
            $this->assertStringContainsString( "'disks' => []", $content );
        }
        finally
        {
            config( ['cms' => $config] );

            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }
}
