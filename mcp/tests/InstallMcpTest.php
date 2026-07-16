<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\InstallMcp;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class InstallMcpTest extends McpTestAbstract
{
    public function testRenamesExistingMcpLimiter() : void
    {
        $path = base_path( 'routes/ai.php' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try
        {
            if( !is_dir( dirname( $path ) ) ) {
                mkdir( dirname( $path ), 0755, true );
            }

            file_put_contents( $path, "<?php Mcp::web('cms', \\Aimeos\\Cms\\Mcp\\CmsServer::class)->middleware('throttle:cms-admin');" );

            $command = new InstallMcp();
            $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );
            ( new \ReflectionMethod( $command, 'limiter' ) )->invoke( $command );

            $content = (string) file_get_contents( $path );

            $this->assertStringContainsString( 'throttle:cms-mcp', $content );
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
