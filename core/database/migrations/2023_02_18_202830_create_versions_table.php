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
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = config('cms.db', 'sqlite');

        Schema::connection($name)->create('cms_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('versionable_id');
            $table->string('versionable_type', 50);
            $table->boolean('published');
            $table->datetime('publish_at')->nullable();
            $table->string('lang', 5)->nullable();
            $table->json('data');
            $table->json('aux');
            $table->string('editor');
            $table->timestamp('created_at', 3);

            $table->index(['versionable_id', 'versionable_type', 'created_at', 'tenant_id'], 'idx_versions_id_type_created_tenantid');
            $table->index(['publish_at', 'published']);
            $table->index(['published', 'lang']);
            $table->index(['id', 'lang']);
            $table->index('editor');
        });

        $db = DB::connection($name);
        $driver = $db->getDriverName();

        if( in_array($driver, ['mysql', 'mariadb']) )
        {
            $db->statement('ALTER TABLE cms_versions
                ADD COLUMN data_type VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.type\'))) VIRTUAL,
                ADD COLUMN data_path VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.path\'))) VIRTUAL,
                ADD COLUMN data_domain VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.domain\'))) VIRTUAL,
                ADD COLUMN data_tag VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.tag\'))) VIRTUAL,
                ADD COLUMN data_theme VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.theme\'))) VIRTUAL,
                ADD COLUMN data_status SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, \'$.status\')) VIRTUAL,
                ADD COLUMN data_cache SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, \'$.cache\')) VIRTUAL,
                ADD COLUMN data_mime VARCHAR(100) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.mime\'))) VIRTUAL
            ');

            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->index('data_type');
                $table->index('data_path');
                $table->index('data_domain');
                $table->index('data_tag');
                $table->index('data_theme');
                $table->index('data_status');
                $table->index('data_cache');
                $table->index('data_mime');
            });

            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain(200), data_path(255))');
        }
        elseif( $driver === 'pgsql' )
        {
            $db->statement("CREATE INDEX cms_versions_data_type_index ON cms_versions ((data->>'type'))");
            $db->statement("CREATE INDEX cms_versions_data_path_index ON cms_versions ((data->>'path'))");
            $db->statement("CREATE INDEX cms_versions_data_domain_index ON cms_versions ((data->>'domain'))");
            $db->statement("CREATE INDEX cms_versions_data_tag_index ON cms_versions ((data->>'tag'))");
            $db->statement("CREATE INDEX cms_versions_data_theme_index ON cms_versions ((data->>'theme'))");
            $db->statement("CREATE INDEX cms_versions_data_status_index ON cms_versions (((data->>'status')::smallint))");
            $db->statement("CREATE INDEX cms_versions_data_cache_index ON cms_versions (((data->>'cache')::smallint))");
            $db->statement("CREATE INDEX cms_versions_data_mime_index ON cms_versions ((data->>'mime'))");
            $db->statement("CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, (data->>'domain'), (data->>'path'))");
        }
        elseif( $driver === 'sqlsrv' )
        {
            $db->statement("ALTER TABLE cms_versions ADD data_type AS CAST(JSON_VALUE(data, '$.type') AS VARCHAR(50))");
            $db->statement("ALTER TABLE cms_versions ADD data_path AS CAST(JSON_VALUE(data, '$.path') AS VARCHAR(255))");
            $db->statement("ALTER TABLE cms_versions ADD data_domain AS CAST(JSON_VALUE(data, '$.domain') AS VARCHAR(255))");
            $db->statement("ALTER TABLE cms_versions ADD data_tag AS CAST(JSON_VALUE(data, '$.tag') AS VARCHAR(30))");
            $db->statement("ALTER TABLE cms_versions ADD data_theme AS CAST(JSON_VALUE(data, '$.theme') AS VARCHAR(30))");
            $db->statement("ALTER TABLE cms_versions ADD data_status AS CAST(JSON_VALUE(data, '$.status') AS SMALLINT)");
            $db->statement("ALTER TABLE cms_versions ADD data_cache AS CAST(JSON_VALUE(data, '$.cache') AS SMALLINT)");
            $db->statement("ALTER TABLE cms_versions ADD data_mime AS CAST(JSON_VALUE(data, '$.mime') AS VARCHAR(100))");

            $db->statement('CREATE INDEX cms_versions_data_type_index ON cms_versions (data_type)');
            $db->statement('CREATE INDEX cms_versions_data_path_index ON cms_versions (data_path)');
            $db->statement('CREATE INDEX cms_versions_data_domain_index ON cms_versions (data_domain)');
            $db->statement('CREATE INDEX cms_versions_data_tag_index ON cms_versions (data_tag)');
            $db->statement('CREATE INDEX cms_versions_data_theme_index ON cms_versions (data_theme)');
            $db->statement('CREATE INDEX cms_versions_data_status_index ON cms_versions (data_status)');
            $db->statement('CREATE INDEX cms_versions_data_cache_index ON cms_versions (data_cache)');
            $db->statement('CREATE INDEX cms_versions_data_mime_index ON cms_versions (data_mime)');
            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain, data_path)');
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_versions');
    }
};
