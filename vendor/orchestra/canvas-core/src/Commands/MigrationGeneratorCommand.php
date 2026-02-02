<?php

namespace Orchestra\Canvas\Core\Commands;

use Illuminate\Console\MigrationGeneratorCommand as Command;
use Orchestra\Canvas\Core\Concerns\MigrationGenerator;

/**
 * @property string|null $name
 * @property string|null $description
 */
abstract class MigrationGeneratorCommand extends Command
{
    use MigrationGenerator;

    /** {@inheritDoc} */
    #[\Override]
    protected function configure()
    {
        parent::configure();

        $this->addGeneratorPresetOptions();
    }

    /** {@inheritDoc} */
    #[\Override]
    protected function createBaseMigration($table)
    {
        return $this->createBaseMigrationUsingCanvas($table);
    }

    /** {@inheritDoc} */
    #[\Override]
    protected function migrationExists($table)
    {
        return $this->migrationExistsUsingCanvas($table);
    }
}
