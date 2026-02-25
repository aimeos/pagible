<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Support\Env;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Input\InputOption;

use function Illuminate\Support\php_binary;


class Serve extends ServeCommand
{
    /**
     * Command name
     */
    protected $name = 'cms:serve';

    /**
     * Command description
     */
    protected $description = 'Serve the application for CMS development';


    /**
     * Get the full server command.
     *
     * @return array<int, string>
     */
    protected function serverCommand()
    {
        return [
            php_binary(),
            '-S',
            $this->host().':'.$this->port(),
            '-d',
            'upload_max_filesize=100M',
            '-d',
            'post_max_size=100M',
            __DIR__.'/../server.php',
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions()
    {
        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', Env::get('SERVER_HOST', 'localhost')],
            ['port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', Env::get('SERVER_PORT')],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'The max number of ports to attempt to serve from', 10],
            ['no-reload', null, InputOption::VALUE_NONE, 'Do not reload the development server on .env file changes'],
        ];
    }
}
