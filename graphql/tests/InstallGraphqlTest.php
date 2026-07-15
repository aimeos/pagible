<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\InstallGraphql;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class InstallGraphqlTest extends GraphqlTestAbstract
{
    public function testRenamesExistingGraphqlLimiter() : void
    {
        $path = base_path( 'graphql/cms.graphql' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try
        {
            if( !is_dir( dirname( $path ) ) ) {
                mkdir( dirname( $path ), 0755, true );
            }

            file_put_contents( $path, 'type Query { me: User @throttle(name: "cms-admin") }' );

            $command = new InstallGraphql();
            $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );
            ( new \ReflectionMethod( $command, 'limiter' ) )->invoke( $command );

            $content = (string) file_get_contents( $path );

            $this->assertStringContainsString( 'cms-graphql', $content );
            $this->assertStringNotContainsString( 'cms-admin', $content );
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
