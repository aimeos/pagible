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
                page_id UNINDEXED,
                tenant_id UNINDEXED,
                content
            )");
        }
        else
        {
            $schema->create('cms_index', function (Blueprint $table) use ($driver) {

                if( $driver === 'sqlsrv' ) {
                    $table->id()->primary('pk_cms_index');
                }

                $table->uuid('page_id');
                $table->string('tenant_id', 250);
                $table->text('content');

                $table->index(['tenant_id']);
                $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('cascade');

                if( in_array($driver, ['mariadb', 'mysql']) ) {
                    $table->fullText('content');
                }
            });


            if( $driver === 'sqlsrv' )
            {
                $db->statement('CREATE FULLTEXT CATALOG cms_index_catalog AS DEFAULT');

                try {
                    $db->statement('CREATE FULLTEXT INDEX ON cms_index(content) KEY INDEX pk_cms_index ON cms_index_catalog');
                } catch( \Exception $e ) {
                    echo "SQL Server fulltext search not supported, skipping fulltext index\n";
                }
            }
        }
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
            try {
                $db->statement('DROP FULLTEXT INDEX ON cms_index');
            } catch( \Exception $e ) {
                echo "SQL Server fulltext search not supported, skip dropping fulltext index\n";
            }

            $db->statement('DROP FULLTEXT CATALOG cms_index_catalog');
        }

        $db->statement('DROP TABLE IF EXISTS cms_index');
    }
};
