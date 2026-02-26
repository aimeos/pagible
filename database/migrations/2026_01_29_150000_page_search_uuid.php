<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(config('cms.db', 'sqlite'));

        if( in_array( $schema->getColumnType('cms_page_search', 'id'), ['varchar', 'char', 'uniqueidentifier', 'uuid'] ) ) {
            return;
        }

        Schema::connection( config( 'cms.db', 'sqlite' ) )->create( 'cms_page_search_new', function ( Blueprint $table ) {
            $collation = match( Schema::getConnection()->getDriverName() ) {
                'mysql' => 'utf8mb4_bin',
                'mariadb' => 'utf8mb4_bin',
                'sqlite' => 'BINARY',
                'sqlsrv' => 'Latin1_General_100_CS_AS_SC_UTF8',
                default => null,
            };

            $table->uuid('id')->primary();
            $table->bigInteger( 'page_id' ); // will be changed and constrainted later
            $table->string( 'tenant_id', 250 );
            $table->string( 'lang', 5 );
            $table->string( 'domain' );
            $table->string( 'path' )->collation( $collation );
            $table->string( 'title' );
            $table->text( 'content' );

            $table->unique( [ 'tenant_id', 'lang', 'domain', 'path' ] );

            if ( in_array( Schema::getConnection()->getDriverName(), [ 'mariadb', 'mysql' ] ) ) {
                $table->fullText( 'content' );
            }
        } );

        DB::table('cms_page_search_new')->insert(
            DB::table('cms_page_search')->get()->map(function ($row) {
                $row->id = Str::uuid()->toString();
                return (array) $row;
            })->toArray()
        );

        $schema->dropIfExists('cms_page_search');
        $schema->rename('cms_page_search_new', 'cms_page_search');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // removed by previous migration
    }
};
