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
    public function testRenamesExistingBroadcastLimiter() : void
    {
        $path = base_path( 'config/cms.php' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try
        {
            if( !is_dir( dirname( $path ) ) ) {
                mkdir( dirname( $path ), 0755, true );
            }

            file_put_contents( $path, "<?php return ['broadcast-middleware' => ['throttle:cms-admin']];" );

            $command = new InstallCore();
            $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );
            ( new \ReflectionMethod( $command, 'broadcast' ) )->invoke( $command );

            $content = (string) file_get_contents( $path );

            $this->assertStringContainsString( 'throttle:cms-broadcast', $content );
            $this->assertStringNotContainsString( 'throttle:cms-admin', $content );
        }
        finally
        {
            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }
}
