<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class PatchesFilesTest extends CoreTestAbstract
{
    private string $filename = 'storage/patches-files-test.txt';
    private BufferedOutput $buffer;
    private Command $command;


    protected function setUp() : void
    {
        parent::setUp();

        $this->buffer = new BufferedOutput();
        $this->command = new class extends Command {
            use PatchesFiles {
                append as public;
                insert as public;
                patch as public;
            }
        };
        $this->command->setOutput( new OutputStyle( new ArrayInput( [] ), $this->buffer ) );

        if( !is_dir( dirname( base_path( $this->filename ) ) ) ) {
            mkdir( dirname( base_path( $this->filename ) ), 0755, true );
        }
    }


    protected function tearDown() : void
    {
        @unlink( base_path( $this->filename ) );
        parent::tearDown();
    }


    public function testAppend() : void
    {
        file_put_contents( base_path( $this->filename ), 'start' );

        $this->assertSame( 0, $this->command->append( $this->filename, '#import cms.graphql' ) );
        $this->assertSame( 0, $this->command->append( $this->filename, '#import cms.graphql' ) );

        $this->assertSame( "start\n\n#import cms.graphql", file_get_contents( base_path( $this->filename ) ) );

        $output = $this->buffer->fetch();
        $this->assertStringContainsString( "File [{$this->filename}] updated", $output );
        $this->assertStringContainsString( "File [{$this->filename}] already up to date", $output );
    }


    public function testInsert() : void
    {
        file_put_contents( base_path( $this->filename ), "['a' => [],\n'b' => [],\n];" );

        $this->assertSame( 0, $this->command->insert( $this->filename, '],', "\n'c' => [],", "'c'", 'Added to [%1$s]', true ) );
        $this->assertSame( 0, $this->command->insert( $this->filename, '],', "\n'c' => [],", "'c'", 'Added to [%1$s]', true ) );

        $this->assertSame( "['a' => [],\n'b' => [],\n'c' => [],\n];", file_get_contents( base_path( $this->filename ) ) );

        $output = $this->buffer->fetch();
        $this->assertSame( 1, substr_count( $output, "Added to [{$this->filename}]" ) );
        $this->assertStringNotContainsString( 'updated', $output );
        $this->assertStringContainsString( 'already up to date', $output );
    }


    public function testInsertFirst() : void
    {
        file_put_contents( base_path( $this->filename ), "['a' => [],\n'b' => [],\n];" );

        $this->assertSame( 0, $this->command->insert( $this->filename, '],', "\n'c' => [],", "'c'", 'Added to [%1$s]' ) );
        $this->assertSame( "['a' => [],\n'c' => [],\n'b' => [],\n];", file_get_contents( base_path( $this->filename ) ) );
    }


    public function testPatchFailure() : void
    {
        file_put_contents( base_path( $this->filename ), 'content' );

        $this->assertSame( 1, $this->command->patch( $this->filename, fn( string $content ) => null ) );
        $this->assertSame( 'content', file_get_contents( base_path( $this->filename ) ) );
        $this->assertSame( '', $this->buffer->fetch() );
    }
}
