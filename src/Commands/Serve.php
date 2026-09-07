<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Support\Env;
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


    /**
     * Map configured public links to their canonical target directories.
     *
     * @return array<string, string>
     */
    protected function links(): array
    {
        $links = [];
        $public = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        foreach (config('filesystems.links') ?? [public_path('storage') => storage_path('app/public')] as $link => $target) {
            if (!str_starts_with($link, $public) || !is_link($link)) {
                continue;
            }

            if (($root = realpath($target)) && is_dir($root) && realpath($link) === $root) {
                $uri = '/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($link, strlen($public))).'/';
                $links[$uri] = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            }
        }

        // Match nested mounts before their parents.
        uksort($links, fn($a, $b) => strlen($b) <=> strlen($a));

        return $links;
    }


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
            '-d',
            'cms.serve.links='.base64_encode(json_encode($this->links(), JSON_THROW_ON_ERROR)),
            __DIR__.'/../server.php',
        ];
    }
}
