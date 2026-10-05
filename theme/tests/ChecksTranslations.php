<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Translation\MessageSelector;


/**
 * Checks JSON translation files for missing keys, placeholders and plural forms.
 */
trait ChecksTranslations
{
    /**
     * Asserts that all language files in the directory are complete and consistent.
     *
     * @param string $langDir Directory containing the JSON language files
     * @param array<int, string> $sourceDirs Directories with views and classes using the keys
     * @param array<int, string> $sharedKeys Keys translated by other packages
     */
    protected function assertTranslations( string $langDir, array $sourceDirs, array $sharedKeys = [] ) : void
    {
        $en = $this->translations( $langDir . '/en.json' );

        foreach( $this->usedKeys( $sourceDirs ) as $key => $file ) {
            $this->assertTrue( isset( $en[$key] ) || in_array( $key, $sharedKeys, true ), sprintf( 'Key "%1$s" used in %2$s is missing in en.json', $key, $file ) );
        }

        $selector = new MessageSelector();

        foreach( glob( $langDir . '/*.json' ) ?: [] as $path )
        {
            $lang = basename( $path, '.json' );
            $translations = $this->translations( $path );

            $this->assertEquals( [], array_diff( array_keys( $en ), array_keys( $translations ) ), $lang . ': missing keys' );
            $this->assertEquals( [], array_diff( array_keys( $translations ), array_keys( $en ) ), $lang . ': unknown keys' );

            foreach( $translations as $key => $value )
            {
                $this->assertNotSame( '', trim( $value ), sprintf( '%1$s: empty translation for "%2$s"', $lang, $key ) );
                $this->assertEquals( $this->placeholders( (string) $key ), $this->placeholders( $value ), sprintf( '%1$s: placeholders differ for "%2$s"', $lang, $key ) );

                $segments = explode( '|', $value );

                if( str_contains( (string) $key, '|' ) && !preg_match( '/^\s*[\{\[]/', $segments[0] ) )
                {
                    $forms = max( array_map( fn( $num ) => $selector->getPluralIndex( $lang, $num ), range( 0, 200 ) ) ) + 1;
                    $this->assertCount( $forms, $segments, sprintf( '%1$s: wrong number of plural forms for "%2$s"', $lang, $key ) );
                }
            }
        }
    }


    /**
     * Returns the sorted placeholder names in the given text.
     *
     * @return array<int, string>
     */
    private function placeholders( string $text ) : array
    {
        preg_match_all( '/:([a-z_]+)/i', $text, $matches );

        $names = array_values( array_unique( $matches[1] ) );
        sort( $names );

        return $names;
    }


    /**
     * Returns the translations from the JSON file.
     *
     * @return array<string, string>
     */
    private function translations( string $path ) : array
    {
        $data = json_decode( (string) file_get_contents( $path ), true );
        $this->assertIsArray( $data, 'Invalid JSON in ' . $path );

        return $data;
    }


    /**
     * Returns the literal translation keys used in the PHP and Blade files.
     *
     * @param array<int, string> $dirs Directories to scan recursively
     * @return array<string, string> Translation keys mapped to the first file using them
     */
    private function usedKeys( array $dirs ) : array
    {
        $keys = [];

        foreach( $dirs as $dir )
        {
            $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

            foreach( $files as $file )
            {
                if( $file->getExtension() !== 'php' ) {
                    continue;
                }

                preg_match_all( '/(?:\b__|\btrans_choice|@lang)\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'\s*[,)]/', (string) file_get_contents( $file->getPathname() ), $matches );

                foreach( $matches[1] as $key ) {
                    $keys[stripslashes( $key )] ??= $file->getPathname();
                }
            }
        }

        return $keys;
    }
}
