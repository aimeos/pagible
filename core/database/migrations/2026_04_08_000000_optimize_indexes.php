<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /** Name of the index for looking up page versions by domain and path */
    private const DOMAIN_PATH_INDEX = 'cms_versions_tenantid_versionabletype_datadomain_datapath_index';

    /** SQL types of the JSON fields stored in generated columns by MySQL and SQL Server */
    private const GENERATED = [
        'type'   => 'VARCHAR(50)',
        'path'   => 'VARCHAR(255)',
        'domain' => 'VARCHAR(255)',
        'theme'  => 'VARCHAR(30)',
        'status' => 'SMALLINT',
        'cache'  => 'SMALLINT',
        'name'   => 'VARCHAR(255)',
        'tag'    => 'VARCHAR(30)',
        'to'     => 'VARCHAR(255)',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $name   = \Aimeos\Cms\DB::name();
        $schema = Schema::connection($name);
        $db     = DB::connection($name);
        $driver = $db->getDriverName();

        $this->pages($schema, $driver);
        $this->elements($schema);
        $this->files($schema);
        $this->versions($schema, $db, $driver);
        $this->pivots($schema);
    }


    private function addIndex(Builder $schema, string $table, array $cols, ?string $name = null): void
    {
        $idxName = $name ?? $table . '_' . implode('_', $cols) . '_index';

        if ($this->hasIndex($schema, $table, $idxName)) {
            return;
        }

        $schema->table($table, function (Blueprint $t) use ($cols, $name) {
            $name === null ? $t->index($cols) : $t->index($cols, $name);
        });
    }


    private function dropLegacyVersionIndexes(Builder $schema, Connection $db, string $driver): void
    {
        $names  = $this->indexNames($schema, 'cms_versions');
        $legacy = [
            'cms_versions_editor_index',
            'cms_versions_lang_index',
            'cms_versions_editor_id_index',
            'cms_versions_lang_tenant_id_id_index',
            'cms_versions_editor_tenant_id_id_index',
            'cms_versions_tenant_id_editor_id_index',
            'cms_versions_tenant_id_lang_id_index',
            self::DOMAIN_PATH_INDEX,
            'cms_versions_id_covering_index',
        ];

        foreach (['type', 'path', 'domain', 'tag', 'theme', 'status', 'cache', 'mime', 'scheduled', 'name'] as $f) {
            array_push($legacy, "cms_versions_data_{$f}_id_index", "cms_versions_data_{$f}_tenant_id_id_index", "cms_versions_data_{$f}_index");
        }

        foreach ($legacy as $idx) {
            $this->dropVersionIndex($schema, $db, $driver, $names, $idx);
        }
    }


    private function dropUnusedVersionIndexes(Builder $schema, Connection $db, string $driver): void
    {
        $names = $this->indexNames($schema, 'cms_versions');

        foreach (['cms_versions_data_domain_index', 'cms_versions_data_domain_tenant_id_id_index'] as $idx) {
            $this->dropVersionIndex($schema, $db, $driver, $names, $idx);
        }
    }


    private function dropVersionIndex(Builder $schema, Connection $db, string $driver, array $names, string $idx): void
    {
        if (!in_array($idx, $names, true)) {
            return;
        }

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $db->statement("DROP INDEX IF EXISTS $idx");
        } elseif ($driver === 'sqlsrv') {
            $db->statement("DROP INDEX IF EXISTS $idx ON cms_versions");
        } else {
            $schema->table('cms_versions', function (Blueprint $table) use ($idx) {
                $table->dropIndex($idx);
            });
        }
    }


    private function elements(Builder $schema): void
    {
        $names = $this->indexNames($schema, 'cms_elements');

        $schema->table('cms_elements', function (Blueprint $table) use ($names) {
            if (in_array('cms_elements_name_tenant_id_index', $names, true)) {
                $table->dropIndex(['name', 'tenant_id']);
            }
            if (in_array('cms_elements_editor_tenant_id_index', $names, true)) {
                $table->dropIndex(['editor', 'tenant_id']);
            }
            if (in_array('cms_elements_tenant_id_deleted_at_index', $names, true)) {
                $table->dropIndex('cms_elements_tenant_id_deleted_at_index');
            }
            if (in_array('cms_elements_lang_tenant_id_index', $names, true)) {
                $table->dropIndex(['lang', 'tenant_id']);
            }
        });

        $this->addIndex($schema, 'cms_elements', ['deleted_at', 'tenant_id']);
        $this->addIndex($schema, 'cms_elements', ['latest_id']);
    }


    private function files(Builder $schema): void
    {
        $names = $this->indexNames($schema, 'cms_files');

        $schema->table('cms_files', function (Blueprint $table) use ($names) {
            if (in_array('cms_files_name_tenant_id_index', $names, true)) {
                $table->dropIndex(['name', 'tenant_id']);
            }
            if (in_array('cms_files_editor_tenant_id_index', $names, true)) {
                $table->dropIndex(['editor', 'tenant_id']);
            }
            if (in_array('cms_files_tenant_id_deleted_at_index', $names, true)) {
                $table->dropIndex('cms_files_tenant_id_deleted_at_index');
            }
            if (in_array('cms_files_lang_tenant_id_index', $names, true)) {
                $table->dropIndex(['lang', 'tenant_id']);
            }
        });

        $this->addIndex($schema, 'cms_files', ['deleted_at', 'tenant_id']);
        $this->addIndex($schema, 'cms_files', ['latest_id']);
    }


    private function hasIndex(Builder $schema, string $table, string $name): bool
    {
        return in_array($name, $this->indexNames($schema, $table), true);
    }


    private function indexNames(Builder $schema, string $table): array
    {
        return collect($schema->getIndexes($table))->pluck('name')->all();
    }


    private function pages(Builder $schema, string $driver): void
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
            'cms_pages_title_tenant_id_index',
            'cms_pages_type_tenant_id_index',
            'cms_pages_tenant_id_parent_id_deleted_at__lft_index',
            'cms_pages_tenant_id_status__lft__rgt_index',
            'cms_pages_tenant_id_depth_deleted_at__lft_index',
            'cms_pages_tenant_id_deleted_at__rgt__lft_index',
            'cms_pages_tenant_id_deleted_at__lft_latest_id_index',
            'cms_pages_covering_index',
            'cms_pages_deleted_at_index',
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
        $this->addIndex($schema, 'cms_pages', ['parent_id', 'deleted_at', 'tenant_id', '_lft']);
        $this->addIndex($schema, 'cms_pages', ['lang', 'tenant_id', 'status']);
        $this->addIndex($schema, 'cms_pages', ['depth', 'deleted_at', 'tenant_id', '_lft']);
        $this->addIndex($schema, 'cms_pages', ['_lft', '_rgt', 'parent_id', 'tenant_id']);
        $this->addIndex($schema, 'cms_pages', ['_rgt', 'tenant_id']);
        $this->addIndex($schema, 'cms_pages', ['tenant_id', 'status', 'deleted_at', '_lft', '_rgt']);

        $this->addIndex($schema, 'cms_pages', ['latest_id']);

        if ($driver === 'sqlite') {
            $this->addIndex(
                $schema,
                'cms_pages',
                ['deleted_at', 'tenant_id', 'depth', '_lft', '_rgt', 'id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config'],
                'cms_pages_covering_index'
            );
        } else {
            $this->addIndex($schema, 'cms_pages', ['deleted_at', 'tenant_id', '_lft', '_rgt', 'latest_id']);
        }
    }


    private function pivots(Builder $schema): void
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
        if ($this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_version_id_index')) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->dropIndex(['element_id', 'version_id']);
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


    private function versions(Builder $schema, Connection $db, string $driver): void
    {
        $this->dropUnusedVersionIndexes($schema, $db, $driver);

        $this->addIndex($schema, 'cms_versions', ['editor', 'tenant_id', 'id']);
        $this->addIndex($schema, 'cms_versions', ['lang', 'tenant_id', 'id']);

        $mysql = in_array($driver, ['mysql', 'mariadb'], true);

        if (!$mysql && !in_array($driver, ['pgsql', 'sqlsrv', 'sqlite'], true)) {
            return;
        }

        // MySQL and SQL Server index generated columns, PostgreSQL and SQLite index the JSON expressions
        if ($mysql || $driver === 'sqlsrv') {
            foreach (self::GENERATED as $field => $type) {
                if (!$schema->hasColumn('cms_versions', "data_$field")) {
                    $db->statement($mysql
                        ? "ALTER TABLE cms_versions ADD COLUMN data_$field " . $this->mysqlColumn($field, $type)
                        : "ALTER TABLE cms_versions ADD data_$field AS CAST(JSON_VALUE(data, '$.$field') AS $type)");
                }
            }
        }

        if ($driver !== 'sqlite') {
            $this->dropLegacyVersionIndexes($schema, $db, $mysql ? 'mysql' : $driver);
        }

        $expr = fn(string $field) => match (true) {
            $mysql, $driver === 'sqlsrv' => "data_$field",
            $driver === 'pgsql' => self::GENERATED[$field] === 'SMALLINT' ? "((data->>'$field')::smallint)" : "(data->>'$field')",
            default => "json_extract(data, '\$.\"$field\"')",
        };

        foreach (['type', 'theme', 'status', 'cache', 'name'] as $field) {
            $idx = "cms_versions_data_{$field}_tenant_id_id_index";

            if (!$this->hasIndex($schema, 'cms_versions', $idx)) {
                $db->statement("CREATE INDEX $idx ON cms_versions ({$expr($field)}, tenant_id, id)");
            }
        }

        if (!$this->hasIndex($schema, 'cms_versions', self::DOMAIN_PATH_INDEX)) {
            $cols = $mysql ? 'data_domain(200), data_path(255)' : $expr('domain') . ', ' . $expr('path');
            $db->statement('CREATE INDEX ' . self::DOMAIN_PATH_INDEX . " ON cms_versions (versionable_type, $cols, tenant_id)");
        }
    }


    private function mysqlColumn(string $field, string $type): string
    {
        $value = "JSON_EXTRACT(data, '$.$field')";
        $value = $type === 'SMALLINT' ? $value : "JSON_UNQUOTE($value)";

        return "$type GENERATED ALWAYS AS ($value) VIRTUAL";
    }
};
