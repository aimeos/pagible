<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;


abstract class ImportTestAbstract extends CmsTestAbstract
{
	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\ImportServiceProvider',
		] );
	}


	/**
	 * Returns the logged queries with the identifiers quoted like in SQLite and PostgreSQL.
	 *
	 * @return Collection<int, array{query: string, bindings: array<mixed>, time: float|null}>
	 */
	protected function queries( Connection $db ): Collection
	{
		return Collection::make( $db->getQueryLog() )->map( fn( $query ) => ['query' => $this->sql( $query['query'] )] + $query );
	}


	/**
	 * Returns the SQL statement with the identifiers quoted and the row limit added like in SQLite and PostgreSQL.
	 *
	 * MariaDB and MySQL quote identifiers with backticks, SQL Server with brackets and uses "top" instead of "limit".
	 */
	protected function sql( string $sql ): string
	{
		$sql = strtr( $sql, ['`' => '"', '[' => '"', ']' => '"'] );
		return (string) preg_replace( '/^select top (\d+) (.*)$/s', 'select $2 limit $1', $sql );
	}
}
