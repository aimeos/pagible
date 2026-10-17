<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Plugin;
use Gettext\Loader\PoLoader;


class AdminCatalogTest extends ThemeTestAbstract
{
	public function testRegistersCatalog() : void
	{
		$this->assertSame( '/vendor/cms/theme/i18n/{locale}.json', Plugin::all()['i18n']['cms'] ?? null );
	}


	public function testCatalogsAreCompiled() : void
	{
		$dirs = array_filter( [dirname( __DIR__ ), ...glob( dirname( __DIR__, 2 ) . '/themes/*' )],
			fn( $dir ) => is_file( $dir . '/i18n/messages.pot' )
		);

		$this->assertNotEmpty( $dirs );

		foreach( $dirs as $dir )
		{
			$expected = $this->ids( $dir . '/i18n/messages.pot' );

			foreach( glob( $dir . '/i18n/*.po' ) as $file )
			{
				$locale = basename( $file, '.po' );
				$json = $dir . '/public/i18n/' . $locale . '.json';
				$messages = [];

				foreach( ( new PoLoader() )->loadFile( $file ) as $entry )
				{
					if( $entry->isTranslated() ) {
						$messages[$entry->getOriginal()][$entry->getContext()] = $entry->getTranslation();
					}
				}

				$this->assertSame( $expected, $this->ids( $file ), "Outdated $file, run 'php bin/schema-i18n extract'" );
				$this->assertFileExists( $json );
				$this->assertSame( [$locale => $messages], json_decode( file_get_contents( $json ), true ), "Outdated $json, run 'php bin/schema-i18n compile'" );
			}
		}
	}


	public function testThemeCatalogsSkipSharedEntries() : void
	{
		$root = dirname( __DIR__, 2 );
		$shared = array_flip( [...$this->ids( $root . '/admin/i18n/messages.pot' ), ...$this->ids( dirname( __DIR__ ) . '/i18n/messages.pot' )] );

		foreach( glob( $root . '/themes/*/i18n/messages.pot' ) as $file ) {
			$this->assertSame( [], array_values( array_filter( $this->ids( $file ), fn( $id ) => isset( $shared[$id] ) ) ), "Shared entries in $file" );
		}
	}


	public function testThemeCatalogsMoveCommonEntries() : void
	{
		$counts = [];

		foreach( glob( dirname( __DIR__, 2 ) . '/themes/*/i18n/messages.pot' ) as $file )
		{
			foreach( $this->ids( $file ) as $id ) {
				$counts[$id] = ( $counts[$id] ?? 0 ) + 1;
			}
		}

		$this->assertSame( [], array_keys( array_filter( $counts, fn( $count ) => $count >= 3 ) ), "Entries used by three or more themes must be in the base theme catalog" );
	}


	public function testThemeCatalogsUsePrefixedTypes() : void
	{
		$file = dirname( __DIR__, 2 ) . '/themes/aura/i18n/messages.pot';

		if( !is_file( $file ) ) {
			$this->markTestSkipped( 'Aura theme not available' );
		}

		$ids = $this->ids( $file );

		$this->assertContains( "st\x04aura::profile", $ids );
		$this->assertNotContains( "st\x04profile", $ids );
	}


	/**
	 * Returns the sorted context and message IDs of the gettext file.
	 */
	protected function ids( string $file ) : array
	{
		$ids = [];

		foreach( ( new PoLoader() )->loadFile( $file ) as $entry ) {
			$ids[] = $entry->getContext() . "\x04" . $entry->getOriginal();
		}

		sort( $ids );
		return $ids;
	}
}
