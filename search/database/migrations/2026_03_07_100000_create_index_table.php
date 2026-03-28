<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);
        $db = $schema->getConnection();
        $driver = $db->getDriverName();

        if( $driver === 'sqlite' )
        {
            $db->statement("CREATE VIRTUAL TABLE cms_index USING fts5(
                indexable_id UNINDEXED,
                indexable_type UNINDEXED,
                tenant_id UNINDEXED,
                latest UNINDEXED,
                content
            )");
        }
        else
        {
            $schema->create('cms_index', function (Blueprint $table) use ($driver) {

                if( $driver === 'sqlsrv' ) {
                    $table->id()->primary('pk_cms_index');
                }

                $table->uuid('indexable_id');
                $table->string('indexable_type', 50);
                $table->string('tenant_id');
                $table->boolean('latest')->default(false);
                $table->text('content');

                $table->index(['indexable_id', 'indexable_type', 'latest', 'tenant_id']);

                if( in_array($driver, ['mariadb', 'mysql']) ) {
                    $table->fullText('content');
                }
            });


            if( $driver === 'sqlsrv' )
            {
                $db->statement("IF NOT EXISTS (SELECT 1 FROM sys.fulltext_catalogs WHERE name = 'cms_index_catalog') CREATE FULLTEXT CATALOG cms_index_catalog AS DEFAULT");
                $db->statement('CREATE FULLTEXT INDEX ON cms_index(content) KEY INDEX pk_cms_index ON cms_index_catalog');
            }
        }

        $schema->dropIfExists('cms_page_search');

        Artisan::call('cms:index');
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);
        $db = $schema->getConnection();
        $driver = $db->getDriverName();

        if( $driver === 'sqlsrv' )
        {
            $db->statement("IF EXISTS (SELECT 1 FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID('cms_index')) DROP FULLTEXT INDEX ON cms_index");
            $db->statement("IF EXISTS (SELECT 1 FROM sys.fulltext_catalogs WHERE name = 'cms_index_catalog') DROP FULLTEXT CATALOG cms_index_catalog");
        }

        $db->statement('DROP TABLE IF EXISTS cms_index');
    }
};
