<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_versions');
    }

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
            $table->index(['editor', 'tenant_id', 'id']);
            $table->index(['lang', 'tenant_id', 'id']);
        });

        $db = DB::connection($name);
        $driver = $db->getDriverName();

        if( in_array($driver, ['mysql', 'mariadb']) ) {
            $this->mysql($name, $db);
        } elseif( $driver === 'pgsql' ) {
            $this->pgsql($db);
        } elseif( $driver === 'sqlsrv' ) {
            $this->sqlsrv($db);
        } elseif( $driver === 'sqlite' ) {
            $this->sqlite($db);
        }
    }


    private function mysql(string $name, Connection $db): void
    {
        $db->statement('ALTER TABLE cms_versions
            ADD COLUMN data_type VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.type\'))) VIRTUAL,
            ADD COLUMN data_path VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.path\'))) VIRTUAL,
            ADD COLUMN data_domain VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.domain\'))) VIRTUAL,
            ADD COLUMN data_theme VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.theme\'))) VIRTUAL,
            ADD COLUMN data_status SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, \'$.status\')) VIRTUAL,
            ADD COLUMN data_cache SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, \'$.cache\')) VIRTUAL,
            ADD COLUMN data_mime VARCHAR(100) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.mime\'))) VIRTUAL,
            ADD COLUMN data_scheduled SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, \'$.scheduled\')) VIRTUAL,
            ADD COLUMN data_name VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.name\'))) VIRTUAL,
            ADD COLUMN data_tag VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.tag\'))) VIRTUAL,
            ADD COLUMN data_to VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, \'$.to\'))) VIRTUAL
        ');

        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->index(['data_type', 'tenant_id', 'id']);
            $table->index(['data_theme', 'tenant_id', 'id']);
            $table->index(['data_status', 'tenant_id', 'id']);
            $table->index(['data_cache', 'tenant_id', 'id']);
            $table->index(['data_mime', 'tenant_id', 'id']);
            $table->index(['data_scheduled', 'tenant_id', 'id']);
            $table->index(['data_name', 'tenant_id', 'id']);
        });

        $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (versionable_type, data_domain(200), data_path(255), tenant_id)');
    }


    private function pgsql(Connection $db): void
    {
        $db->statement("CREATE INDEX cms_versions_data_type_tenant_id_id_index ON cms_versions ((data->>'type'), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_theme_tenant_id_id_index ON cms_versions ((data->>'theme'), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_status_tenant_id_id_index ON cms_versions (((data->>'status')::smallint), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_cache_tenant_id_id_index ON cms_versions (((data->>'cache')::smallint), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_mime_tenant_id_id_index ON cms_versions ((data->>'mime'), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_scheduled_tenant_id_id_index ON cms_versions (((data->>'scheduled')::smallint), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_data_name_tenant_id_id_index ON cms_versions ((data->>'name'), tenant_id, id)");
        $db->statement("CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (versionable_type, (data->>'domain'), (data->>'path'), tenant_id)");
    }


    private function sqlite(Connection $db): void
    {
        $db->statement('CREATE INDEX cms_versions_data_type_tenant_id_id_index ON cms_versions (json_extract(data, \'$."type"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_theme_tenant_id_id_index ON cms_versions (json_extract(data, \'$."theme"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_status_tenant_id_id_index ON cms_versions (json_extract(data, \'$."status"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_cache_tenant_id_id_index ON cms_versions (json_extract(data, \'$."cache"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_mime_tenant_id_id_index ON cms_versions (json_extract(data, \'$."mime"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_scheduled_tenant_id_id_index ON cms_versions (json_extract(data, \'$."scheduled"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_name_tenant_id_id_index ON cms_versions (json_extract(data, \'$."name"\'), tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (versionable_type, json_extract(data, \'$."domain"\'), json_extract(data, \'$."path"\'), tenant_id)');
    }


    private function sqlsrv(Connection $db): void
    {
        $db->statement("ALTER TABLE cms_versions ADD data_type AS CAST(JSON_VALUE(data, '$.type') AS VARCHAR(50))");
        $db->statement("ALTER TABLE cms_versions ADD data_path AS CAST(JSON_VALUE(data, '$.path') AS VARCHAR(255))");
        $db->statement("ALTER TABLE cms_versions ADD data_domain AS CAST(JSON_VALUE(data, '$.domain') AS VARCHAR(255))");
        $db->statement("ALTER TABLE cms_versions ADD data_theme AS CAST(JSON_VALUE(data, '$.theme') AS VARCHAR(30))");
        $db->statement("ALTER TABLE cms_versions ADD data_status AS CAST(JSON_VALUE(data, '$.status') AS SMALLINT)");
        $db->statement("ALTER TABLE cms_versions ADD data_cache AS CAST(JSON_VALUE(data, '$.cache') AS SMALLINT)");
        $db->statement("ALTER TABLE cms_versions ADD data_mime AS CAST(JSON_VALUE(data, '$.mime') AS VARCHAR(100))");
        $db->statement("ALTER TABLE cms_versions ADD data_scheduled AS CAST(JSON_VALUE(data, '$.scheduled') AS SMALLINT)");
        $db->statement("ALTER TABLE cms_versions ADD data_name AS CAST(JSON_VALUE(data, '$.name') AS VARCHAR(255))");
        $db->statement("ALTER TABLE cms_versions ADD data_tag AS CAST(JSON_VALUE(data, '$.tag') AS VARCHAR(30))");
        $db->statement("ALTER TABLE cms_versions ADD data_to AS CAST(JSON_VALUE(data, '$.to') AS VARCHAR(255))");

        $db->statement('CREATE INDEX cms_versions_data_type_tenant_id_id_index ON cms_versions (data_type, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_theme_tenant_id_id_index ON cms_versions (data_theme, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_status_tenant_id_id_index ON cms_versions (data_status, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_cache_tenant_id_id_index ON cms_versions (data_cache, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_mime_tenant_id_id_index ON cms_versions (data_mime, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_scheduled_tenant_id_id_index ON cms_versions (data_scheduled, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_data_name_tenant_id_id_index ON cms_versions (data_name, tenant_id, id)');
        $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (versionable_type, data_domain, data_path, tenant_id)');
    }
};
