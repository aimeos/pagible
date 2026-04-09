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
     */
    public function up(): void
    {
        $name   = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);
        $db     = DB::connection($name);
        $driver = $db->getDriverName();

        $this->pages($schema, $driver);
        $this->elements($schema);
        $this->files($schema);
        $this->versions($schema, $db, $driver);
        $this->pivots($schema);
    }


    private function pages($schema, string $driver): void
    {
        $names = $this->indexNames($schema, 'cms_pages');

        $drops = [
            'cms_pages_new_theme_tenant_id_index',
            'cms_pages_new_cache_tenant_id_index',
            'cms_pages_new_to_tenant_id_index',
            'cms_pages_new_editor_tenant_id_index',
            'cms_pages_new_name_tenant_id_index',
            'cms_pages_new_related_id_tenant_id_index',
            'cms_pages_new_parent_id_tenant_id_index',
            'cms_pages_new_domain_tenant_id_index',
            'cms_pages_new_title_tenant_id_index',
            'cms_pages_new_type_tenant_id_index',
            'cms_pages_new_deleted_at_index',
            'cms_pages_new_parent_id_index',
            'cms_pages_new__lft__rgt_parent_id_index',
            'cms_pages_new__lft__rgt_tenant_id_status_index',
            'cms_pages_new_tag_lang_tenant_id_status_index',
            'cms_pages_new_lang_tenant_id_status_index',
            'cms_pages_theme_tenant_id_index',
            'cms_pages_cache_tenant_id_index',
            'cms_pages_to_tenant_id_index',
            'cms_pages_editor_tenant_id_index',
            'cms_pages_name_tenant_id_index',
            'cms_pages_related_id_tenant_id_index',
            'cms_pages__lft__rgt_tenant_id_status_index',
            'cms_pages_tenant_id_deleted_at__lft__rgt_index',
            'cms_pages_parent_id_tenant_id_index',
            'cms_pages_tenant_id_parent_id_deleted_at_index',
        ];

        $schema->table('cms_pages', function (Blueprint $table) use ($drops, $names) {
            foreach ($drops as $idx) {
                if (in_array($idx, $names, true)) {
                    $table->dropIndex($idx);
                }
            }
        });

        if (in_array('cms_pages_new_path_domain_tenant_id_unique', $names, true)) {
            $schema->table('cms_pages', function (Blueprint $table) {
                $table->dropUnique('cms_pages_new_path_domain_tenant_id_unique');
                $table->unique(['path', 'domain', 'tenant_id']);
            });
        }

        $this->addIndex($schema, 'cms_pages', ['tag', 'lang', 'tenant_id', 'status']);
        $this->addIndex($schema, 'cms_pages', ['tenant_id', 'parent_id', 'deleted_at', '_lft']);
        $this->addIndex($schema, 'cms_pages', ['lang', 'tenant_id', 'status']);
        $this->addIndex($schema, 'cms_pages', ['domain', 'tenant_id']);
        $this->addIndex($schema, 'cms_pages', ['title', 'tenant_id']);
        $this->addIndex($schema, 'cms_pages', ['type', 'tenant_id']);
        $this->addIndex($schema, 'cms_pages', ['deleted_at']);
        $this->addIndex($schema, 'cms_pages', ['latest_id']);
        $this->addIndex($schema, 'cms_pages', ['tenant_id', 'status', '_lft', '_rgt']);
        $this->addIndex($schema, 'cms_pages', ['tenant_id', 'depth', 'deleted_at', '_lft']);
        $this->addIndex($schema, 'cms_pages', ['tenant_id', 'deleted_at', '_rgt', '_lft']);
        $this->addIndex($schema, 'cms_pages', ['_lft', '_rgt', 'parent_id', 'tenant_id']);

        if ($driver === 'sqlite') {
            $this->addIndex(
                $schema,
                'cms_pages',
                ['tenant_id', 'deleted_at', 'depth', '_lft', '_rgt', 'id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config'],
                'cms_pages_covering_index'
            );
        } else {
            $this->addIndex($schema, 'cms_pages', ['tenant_id', 'deleted_at', '_lft', 'latest_id']);
        }
    }


    private function elements($schema): void
    {
        $names = $this->indexNames($schema, 'cms_elements');

        $schema->table('cms_elements', function (Blueprint $table) use ($names) {
            if (in_array('cms_elements_name_tenant_id_index', $names, true)) {
                $table->dropIndex(['name', 'tenant_id']);
            }
            if (in_array('cms_elements_editor_tenant_id_index', $names, true)) {
                $table->dropIndex(['editor', 'tenant_id']);
            }
        });

        $this->addIndex($schema, 'cms_elements', ['tenant_id', 'deleted_at']);
    }


    private function files($schema): void
    {
        $names = $this->indexNames($schema, 'cms_files');

        $schema->table('cms_files', function (Blueprint $table) use ($names) {
            if (in_array('cms_files_name_tenant_id_index', $names, true)) {
                $table->dropIndex(['name', 'tenant_id']);
            }
            if (in_array('cms_files_editor_tenant_id_index', $names, true)) {
                $table->dropIndex(['editor', 'tenant_id']);
            }
        });

        $this->addIndex($schema, 'cms_files', ['tenant_id', 'deleted_at']);
    }


    private function versions($schema, $db, string $driver): void
    {
        $this->addIndex($schema, 'cms_versions', ['published', 'lang']);
        $this->addIndex($schema, 'cms_versions', ['id', 'lang']);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->versionsMysql($schema, $db);
        } elseif ($driver === 'pgsql') {
            $this->versionsPgsql($schema, $db);
        } elseif ($driver === 'sqlsrv') {
            $this->versionsSqlsrv($schema, $db);
        } elseif ($driver === 'sqlite') {
            $this->versionsSqlite($schema, $db);
        }
    }


    private function versionsMysql($schema, $db): void
    {
        $cols = [
            'data_type'      => "VARCHAR(50) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.type'))) VIRTUAL",
            'data_path'      => "VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.path'))) VIRTUAL",
            'data_domain'    => "VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.domain'))) VIRTUAL",
            'data_tag'       => "VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.tag'))) VIRTUAL",
            'data_theme'     => "VARCHAR(30) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.theme'))) VIRTUAL",
            'data_status'    => "SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, '$.status')) VIRTUAL",
            'data_cache'     => "SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, '$.cache')) VIRTUAL",
            'data_mime'      => "VARCHAR(100) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(data, '$.mime'))) VIRTUAL",
            'data_scheduled' => "SMALLINT GENERATED ALWAYS AS (JSON_EXTRACT(data, '$.scheduled')) VIRTUAL",
            'data_name'      => "VARCHAR(255) GENERATED ALWAYS AS (JSON_VALUE(data, '$.name')) VIRTUAL",
        ];

        foreach ($cols as $col => $def) {
            if (!$schema->hasColumn('cms_versions', $col)) {
                $db->statement("ALTER TABLE cms_versions ADD COLUMN $col $def");
            }
        }

        $this->dropLegacyVersionIndexes($schema, $db, false);

        foreach (array_keys($cols) as $col) {
            $field = substr($col, 5);
            $idx   = "cms_versions_data_{$field}_index";

            if (!$this->hasIndex($schema, 'cms_versions', $idx)) {
                $db->statement("CREATE INDEX $idx ON cms_versions (tenant_id, $col, id)");
            }
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_tenantid_versionabletype_datadomain_datapath_index')) {
            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain(200), data_path(255))');
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_id_covering_index')) {
            $db->statement('CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id, lang, editor, data_status)');
        }
    }


    private function versionsPgsql($schema, $db): void
    {
        $this->dropLegacyVersionIndexes($schema, $db, true);

        $exprs = [
            'type'      => "(data->>'type')",
            'path'      => "(data->>'path')",
            'domain'    => "(data->>'domain')",
            'tag'       => "(data->>'tag')",
            'theme'     => "(data->>'theme')",
            'status'    => "((data->>'status')::smallint)",
            'cache'     => "((data->>'cache')::smallint)",
            'mime'      => "(data->>'mime')",
            'scheduled' => "(data->>'scheduled')",
            'name'      => "(data->>'name')",
        ];

        foreach ($exprs as $field => $expr) {
            $idx = "cms_versions_data_{$field}_index";

            if (!$this->hasIndex($schema, 'cms_versions', $idx)) {
                $db->statement("CREATE INDEX $idx ON cms_versions (tenant_id, $expr, id)");
            }
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_tenantid_versionabletype_datadomain_datapath_index')) {
            $db->statement("CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, (data->>'domain'), (data->>'path'))");
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_id_covering_index')) {
            $db->statement("CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id) INCLUDE (lang, editor)");
        }
    }


    private function versionsSqlsrv($schema, $db): void
    {
        $cols = [
            'data_type'      => "AS CAST(JSON_VALUE(data, '$.type') AS VARCHAR(50))",
            'data_path'      => "AS CAST(JSON_VALUE(data, '$.path') AS VARCHAR(255))",
            'data_domain'    => "AS CAST(JSON_VALUE(data, '$.domain') AS VARCHAR(255))",
            'data_tag'       => "AS CAST(JSON_VALUE(data, '$.tag') AS VARCHAR(30))",
            'data_theme'     => "AS CAST(JSON_VALUE(data, '$.theme') AS VARCHAR(30))",
            'data_status'    => "AS CAST(JSON_VALUE(data, '$.status') AS SMALLINT)",
            'data_cache'     => "AS CAST(JSON_VALUE(data, '$.cache') AS SMALLINT)",
            'data_mime'      => "AS CAST(JSON_VALUE(data, '$.mime') AS VARCHAR(100))",
            'data_scheduled' => "AS CAST(JSON_VALUE(data, '$.scheduled') AS BIT)",
            'data_name'      => "AS CAST(JSON_VALUE(data, '$.name') AS VARCHAR(255))",
        ];

        foreach ($cols as $col => $def) {
            if (!$schema->hasColumn('cms_versions', $col)) {
                $db->statement("ALTER TABLE cms_versions ADD $col $def");
            }
        }

        $this->dropLegacyVersionIndexes($schema, $db, true);

        foreach (array_keys($cols) as $col) {
            $field = substr($col, 5);
            $idx   = "cms_versions_data_{$field}_index";

            if (!$this->hasIndex($schema, 'cms_versions', $idx)) {
                $db->statement("CREATE INDEX $idx ON cms_versions (tenant_id, $col, id)");
            }
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_tenantid_versionabletype_datadomain_datapath_index')) {
            $db->statement('CREATE INDEX cms_versions_tenantid_versionabletype_datadomain_datapath_index ON cms_versions (tenant_id, versionable_type, data_domain, data_path)');
        }

        if (!$this->hasIndex($schema, 'cms_versions', 'cms_versions_id_covering_index')) {
            $db->statement('CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id) INCLUDE (lang, editor, data_status)');
        }
    }


    private function versionsSqlite($schema, $db): void
    {
        $exprs = [
            'type'      => "json_extract(data, '\$.\"type\"')",
            'path'      => "json_extract(data, '\$.\"path\"')",
            'domain'    => "json_extract(data, '\$.\"domain\"')",
            'tag'       => "json_extract(data, '\$.\"tag\"')",
            'theme'     => "json_extract(data, '\$.\"theme\"')",
            'status'    => "json_extract(data, '\$.\"status\"')",
            'cache'     => "json_extract(data, '\$.\"cache\"')",
            'mime'      => "json_extract(data, '\$.\"mime\"')",
            'scheduled' => "json_extract(data, '\$.\"scheduled\"')",
            'name'      => "json_extract(data, '\$.\"name\"')",
        ];

        foreach ($exprs as $field => $expr) {
            $idx = "cms_versions_data_{$field}_index";

            if (!$this->hasIndex($schema, 'cms_versions', $idx)) {
                $db->statement("CREATE INDEX $idx ON cms_versions (tenant_id, $expr, id)");
            }
        }
    }


    private function dropLegacyVersionIndexes($schema, $db, bool $rawDrop): void
    {
        $names  = $this->indexNames($schema, 'cms_versions');
        $legacy = [];

        foreach (['type', 'path', 'domain', 'tag', 'theme', 'status', 'cache', 'mime', 'scheduled', 'name'] as $f) {
            $legacy[] = "cms_versions_data_{$f}_id_index";
            $legacy[] = "cms_versions_data_{$f}_tenant_id_id_index";
        }

        $legacy = array_merge($legacy, [
            'cms_versions_editor_index',
            'cms_versions_lang_index',
            'cms_versions_editor_id_index',
            'cms_versions_lang_tenant_id_id_index',
            'cms_versions_editor_tenant_id_id_index',
        ]);

        foreach ($legacy as $idx) {
            if (in_array($idx, $names, true)) {
                if ($rawDrop) {
                    $db->statement("DROP INDEX IF EXISTS $idx");
                } else {
                    $schema->table('cms_versions', function (Blueprint $table) use ($idx) {
                        $table->dropIndex($idx);
                    });
                }
            }
        }
    }


    private function pivots($schema): void
    {
        if (!$this->hasIndex($schema, 'cms_page_element', 'cms_page_element_element_id_index')) {
            $schema->table('cms_page_element', function (Blueprint $table) {
                $table->index('element_id');
            });
        }

        if (!$this->hasIndex($schema, 'cms_page_file', 'cms_page_file_file_id_index')) {
            $schema->table('cms_page_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        if (!$this->hasIndex($schema, 'cms_element_file', 'cms_element_file_file_id_index')) {
            $schema->table('cms_element_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        if ($this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_index')) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->dropIndex(['element_id']);
            });
        }
        if (!$this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_version_id_index')) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->index(['element_id', 'version_id']);
            });
        }

        if ($this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_index')) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->dropIndex(['file_id']);
            });
        }
        if (!$this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_version_id_index')) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->index(['file_id', 'version_id']);
            });
        }
    }


    private function addIndex($schema, string $table, array $cols, ?string $name = null): void
    {
        $idxName = $name ?? $table . '_' . implode('_', $cols) . '_index';

        if ($this->hasIndex($schema, $table, $idxName)) {
            return;
        }

        $schema->table($table, function (Blueprint $t) use ($cols, $name) {
            $name === null ? $t->index($cols) : $t->index($cols, $name);
        });
    }


    private function hasIndex($schema, string $table, string $name): bool
    {
        return in_array($name, $this->indexNames($schema, $table), true);
    }


    private function indexNames($schema, string $table): array
    {
        return collect($schema->getIndexes($table))->pluck('name')->all();
    }
};
