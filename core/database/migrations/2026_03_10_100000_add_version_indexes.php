<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $schema = Schema::connection($name);
        $db = $schema->getConnection();
        $driver = $db->getDriverName();

        $indexes = collect($schema->getIndexes('cms_versions'))->pluck('name')->all();

        if( in_array('cms_versions_published_lang_index', $indexes) ) {
            return;
        }

        // Add simple column indexes on cms_versions
        $schema->table('cms_versions', function (Blueprint $table) {
            $table->index(['published', 'lang']);
            $table->index('editor');
        });

        // Add JSON path indexes (not supported on SQLite)
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

            $schema->table('cms_versions', function (Blueprint $table) {
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

        // Drop unused indexes from cms_pages
        $schema->table('cms_pages', function (Blueprint $table) {
            $indexes = Schema::getIndexes('cms_pages');
            $names = array_column($indexes, 'name');

            !in_array('cms_pages_new_theme_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_theme_tenant_id_index');
            !in_array('cms_pages_new_cache_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_cache_tenant_id_index');
            !in_array('cms_pages_new_to_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_to_tenant_id_index');
            !in_array('cms_pages_new_editor_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_editor_tenant_id_index');
            !in_array('cms_pages_new_name_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_name_tenant_id_index');
            !in_array('cms_pages_new_related_id_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_new_related_id_tenant_id_index');

            !in_array('cms_pages_theme_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_theme_tenant_id_index');
            !in_array('cms_pages_cache_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_cache_tenant_id_index');
            !in_array('cms_pages_to_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_to_tenant_id_index');
            !in_array('cms_pages_editor_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_editor_tenant_id_index');
            !in_array('cms_pages_name_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_name_tenant_id_index');
            !in_array('cms_pages_related_id_tenant_id_index', $names) ?: $table->dropIndex('cms_pages_related_id_tenant_id_index');

            if( in_array('cms_pages_new_path_domain_tenant_id_unique', $names) ) {
                $table->dropUnique('cms_pages_new_path_domain_tenant_id_unique');
                $table->unique(['path', 'domain', 'tenant_id']);
            }

            if( in_array('cms_pages_new__lft__rgt_parent_id_index', $names) ) {
                $table->dropIndex('cms_pages_new__lft__rgt_parent_id_index');
                $table->index(['_lft', '_rgt', 'parent_id', 'tenant_id']);
            }

            if( in_array('cms_pages_new__lft__rgt_tenant_id_status_index', $names) ) {
                $table->dropIndex('cms_pages_new__lft__rgt_tenant_id_status_index');
                $table->index(['_lft', '_rgt', 'tenant_id', 'status']);
            }

            if( in_array('cms_pages_new_tag_lang_tenant_id_status_index', $names) ) {
                $table->dropIndex('cms_pages_new_tag_lang_tenant_id_status_index');
                $table->index(['tag', 'lang', 'tenant_id', 'status']);
            }

            if( in_array('cms_pages_new_lang_tenant_id_status_index', $names) ) {
                $table->dropIndex('cms_pages_new_lang_tenant_id_status_index');
                $table->index(['lang', 'tenant_id', 'status']);
            }

            if( in_array('cms_pages_new_parent_id_tenant_id_index', $names) ) {
                $table->dropIndex('cms_pages_new_parent_id_tenant_id_index');
                $table->index(['parent_id', 'tenant_id']);
            }

            if( in_array('cms_pages_new_domain_tenant_id_index', $names) ) {
                $table->dropIndex('cms_pages_new_domain_tenant_id_index');
                $table->index(['domain', 'tenant_id']);
            }

            if( in_array('cms_pages_new_title_tenant_id_index', $names) ) {
                $table->dropIndex('cms_pages_new_title_tenant_id_index');
                $table->index(['title', 'tenant_id']);
            }

            if( in_array('cms_pages_new_type_tenant_id_index', $names) ) {
                $table->dropIndex('cms_pages_new_type_tenant_id_index');
                $table->index(['type', 'tenant_id']);
            }

            if( in_array('cms_pages_new_deleted_at_index', $names) ) {
                $table->dropIndex('cms_pages_new_deleted_at_index');
                $table->index(['deleted_at']);
            }

            if( in_array('cms_pages_new_parent_id_index', $names) ) {
                $table->dropIndex('cms_pages_new_parent_id_index');
                $table->index(['parent_id']);
            }
        });

        // Drop unused indexes from cms_elements
        $schema->table('cms_elements', function (Blueprint $table) {
            $table->dropIndex(['name', 'tenant_id']);
            $table->dropIndex(['editor', 'tenant_id']);
        });

        // Drop unused indexes from cms_files
        $schema->table('cms_files', function (Blueprint $table) {
            $table->dropIndex(['name', 'tenant_id']);
            $table->dropIndex(['editor', 'tenant_id']);
        });
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

        // Restore dropped indexes on cms_pages
        $schema->table('cms_pages', function (Blueprint $table) {
            $table->index(['theme', 'tenant_id']);
            $table->index(['cache', 'tenant_id']);
            $table->index(['to', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
            $table->index(['name', 'tenant_id']);
            $table->index(['related_id', 'tenant_id']);
        });

        // Restore dropped indexes on cms_elements
        $schema->table('cms_elements', function (Blueprint $table) {
            $table->index(['name', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
        });

        // Restore dropped indexes on cms_files
        $schema->table('cms_files', function (Blueprint $table) {
            $table->index(['name', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
        });

        // Drop JSON path indexes and generated columns
        if( in_array($driver, ['mysql', 'mariadb']) )
        {
            $db->statement('DROP INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions');

            $schema->table('cms_versions', function (Blueprint $table) {
                $table->dropIndex(['data_type']);
                $table->dropIndex(['data_path']);
                $table->dropIndex(['data_domain']);
                $table->dropIndex(['data_tag']);
                $table->dropIndex(['data_theme']);
                $table->dropIndex(['data_status']);
                $table->dropIndex(['data_cache']);
                $table->dropIndex(['data_mime']);
                $table->dropColumn(['data_type', 'data_path', 'data_domain', 'data_tag', 'data_theme', 'data_status', 'data_cache', 'data_mime']);
            });
        }
        elseif( $driver === 'pgsql' )
        {
            $db->statement('DROP INDEX IF EXISTS cms_versions_tenantid_versionabletype_datadomain_datapath_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_path_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_domain_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_tag_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_mime_index');
        }
        elseif( $driver === 'sqlsrv' )
        {
            $db->statement('DROP INDEX IF EXISTS cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_path_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_domain_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_tag_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_mime_index ON cms_versions');

            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_type');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_path');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_domain');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_tag');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_theme');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_status');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_cache');
            $db->statement('ALTER TABLE cms_versions DROP COLUMN IF EXISTS data_mime');
        }

        // Drop simple column indexes on cms_versions
        $schema->table('cms_versions', function (Blueprint $table) {
            $table->dropIndex(['published', 'lang']);
            $table->dropIndex(['editor']);
        });
    }
};
