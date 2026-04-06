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
            $table->index(['tenant_id', 'editor', 'id']);
            $table->index(['tenant_id', 'lang', 'id']);
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
                $table->index(['tenant_id', 'data_type', 'id']);
                $table->index(['tenant_id', 'data_path', 'id']);
                $table->index(['tenant_id', 'data_domain', 'id']);
                $table->index(['tenant_id', 'data_tag', 'id']);
                $table->index(['tenant_id', 'data_theme', 'id']);
                $table->index(['tenant_id', 'data_status', 'id']);
                $table->index(['tenant_id', 'data_cache', 'id']);
                $table->index(['tenant_id', 'data_mime', 'id']);
            });

            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain(200), data_path(255))');
        }
        elseif( $driver === 'pgsql' )
        {
            $db->statement("CREATE INDEX cms_versions_data_type_index ON cms_versions (tenant_id, (data->>'type'), id)");
            $db->statement("CREATE INDEX cms_versions_data_path_index ON cms_versions (tenant_id, (data->>'path'), id)");
            $db->statement("CREATE INDEX cms_versions_data_domain_index ON cms_versions (tenant_id, (data->>'domain'), id)");
            $db->statement("CREATE INDEX cms_versions_data_tag_index ON cms_versions (tenant_id, (data->>'tag'), id)");
            $db->statement("CREATE INDEX cms_versions_data_theme_index ON cms_versions (tenant_id, (data->>'theme'), id)");
            $db->statement("CREATE INDEX cms_versions_data_status_index ON cms_versions (tenant_id, ((data->>'status')::smallint), id)");
            $db->statement("CREATE INDEX cms_versions_data_cache_index ON cms_versions (tenant_id, ((data->>'cache')::smallint), id)");
            $db->statement("CREATE INDEX cms_versions_data_mime_index ON cms_versions (tenant_id, (data->>'mime'), id)");
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

            $db->statement('CREATE INDEX cms_versions_data_type_index ON cms_versions (tenant_id, data_type, id)');
            $db->statement('CREATE INDEX cms_versions_data_path_index ON cms_versions (tenant_id, data_path, id)');
            $db->statement('CREATE INDEX cms_versions_data_domain_index ON cms_versions (tenant_id, data_domain, id)');
            $db->statement('CREATE INDEX cms_versions_data_tag_index ON cms_versions (tenant_id, data_tag, id)');
            $db->statement('CREATE INDEX cms_versions_data_theme_index ON cms_versions (tenant_id, data_theme, id)');
            $db->statement('CREATE INDEX cms_versions_data_status_index ON cms_versions (tenant_id, data_status, id)');
            $db->statement('CREATE INDEX cms_versions_data_cache_index ON cms_versions (tenant_id, data_cache, id)');
            $db->statement('CREATE INDEX cms_versions_data_mime_index ON cms_versions (tenant_id, data_mime, id)');
            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain, data_path)');
        }
        elseif( $driver === 'sqlite' )
        {
            $db->statement('CREATE INDEX cms_versions_data_type_index ON cms_versions (tenant_id, json_extract(data, \'$."type"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_path_index ON cms_versions (tenant_id, json_extract(data, \'$."path"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_domain_index ON cms_versions (tenant_id, json_extract(data, \'$."domain"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_tag_index ON cms_versions (tenant_id, json_extract(data, \'$."tag"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_theme_index ON cms_versions (tenant_id, json_extract(data, \'$."theme"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_status_index ON cms_versions (tenant_id, json_extract(data, \'$."status"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_cache_index ON cms_versions (tenant_id, json_extract(data, \'$."cache"\'), id)');
            $db->statement('CREATE INDEX cms_versions_data_mime_index ON cms_versions (tenant_id, json_extract(data, \'$."mime"\'), id)');
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
