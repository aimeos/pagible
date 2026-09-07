<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\Serve;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;


class ServeTest extends \Orchestra\Testbench\TestCase
{
    private string $directory;
    private ?Process $server = null;


    public static function paths(): array
    {
        return [
            'public image' => ['/image.svg', 'public/image.svg', 'image/svg+xml'],
            'storage image' => ['/storage/image.svg', 'storage/app/public/image.svg', 'image/svg+xml'],
            'raster MIME' => ['/storage/image.png', 'storage/app/public/image.png', 'image/png'],
            'custom link' => ['/media/image.svg', 'media/image.svg', 'image/svg+xml'],
            'nested mount' => ['/storage/nested/image.svg', 'nested/image.svg', 'image/svg+xml'],
            'encoded name and query' => ['/media/image%20+%20icon.svg?v=1', 'media/image + icon.svg', 'image/svg+xml'],
            'CSS MIME' => ['/style.css', 'public/style.css', 'text/css'],
            'JS MIME' => ['/script.js', 'public/script.js', 'application/javascript'],
            'missing asset' => ['/storage/missing.svg', null, null],
            'unconfigured link' => ['/unconfigured/image.svg', null, null],
            'mismatched target' => ['/mismatch/image.svg', null, null],
            'broken link' => ['/broken/image.svg', null, null],
            'URL prefix boundary' => ['/storage-two/image.svg', null, null],
            'public sibling link' => ['/escape/image.svg', null, null],
            'public traversal' => ['/../public-other/image.svg', null, null],
            'encoded public traversal' => ['/%2e%2e/public-other/image.svg', null, null],
            'storage traversal' => ['/storage/../private/image.svg', null, null],
            'encoded storage traversal' => ['/storage/%2e%2e%2fprivate/image.svg', null, null],
            'private nested link' => ['/storage/private/image.svg', null, null],
            'storage sibling link' => ['/storage/sibling/image.svg', null, null],
            'different allowed root' => ['/storage/public/image.svg', null, null],
            'configured link outside public' => ['/../public-other/shared/image.svg', null, null],
        ];
    }


    public function testDefaultLink(): void
    {
        $this->app['config']->set('filesystems.links', null);
        $response = $this->request('/storage/image.svg');

        $this->assertStringContainsString('200 OK', $response);
        $this->assertStringEndsWith(file_get_contents($this->directory.'/storage/app/public/image.svg'), $response);
    }


    public function testEmptyLinks(): void
    {
        $this->app['config']->set('filesystems.links', []);
        $response = $this->request('/storage/image.svg');

        $this->assertStringContainsString('404 Not Found', $response);
        $this->assertStringEndsWith('Laravel fallback', $response);
    }


    public function testNullByte(): void
    {
        $response = $this->request('/storage/image.svg%00');

        $this->assertStringContainsString('400 Bad Request', $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);
    }


    #[DataProvider('paths')]
    public function testPaths(string $uri, ?string $file, ?string $mime): void
    {
        $response = $this->request($uri);
        [$headers, $body] = explode("\r\n\r\n", $response, 2);

        if ($file === null) {
            $this->assertStringContainsString('404 Not Found', $headers);
            $this->assertSame('Laravel fallback', $body);
        } else {
            $this->assertStringContainsString('200 OK', $headers);
            $this->assertStringContainsString('content-type: '.$mime, strtolower($headers));
            $this->assertStringContainsString('access-control-allow-origin: *', strtolower($headers));
            $this->assertSame(file_get_contents($this->directory.'/'.$file), $body);
        }
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/pagible-serve-'.bin2hex(random_bytes(8));

        foreach (['public', 'public-other', 'storage/app/public', 'storage/app/private', 'storage/app/public-other', 'media', 'nested'] as $directory) {
            mkdir($this->directory.'/'.$directory, 0700, true);
            file_put_contents($this->directory.'/'.$directory.'/image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><title>'.$directory.'</title></svg>');
        }

        file_put_contents($this->directory.'/public/index.php', '<?php http_response_code(404); echo "Laravel fallback";');
        file_put_contents($this->directory.'/public/style.css', 'body { color: red; }');
        file_put_contents($this->directory.'/public/script.js', 'document.title = "CMS";');
        copy($this->directory.'/media/image.svg', $this->directory.'/media/image + icon.svg');
        file_put_contents($this->directory.'/storage/app/public/image.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aFz8AAAAASUVORK5CYII='
        ));

        symlink('../storage/app/public', $this->directory.'/public/storage');
        symlink($this->directory.'/media', $this->directory.'/public/media');
        symlink($this->directory.'/nested', $this->directory.'/public/storage/nested');
        symlink($this->directory.'/media', $this->directory.'/public/unconfigured');
        symlink($this->directory.'/media', $this->directory.'/public/storage-two');
        symlink($this->directory.'/storage/app/private', $this->directory.'/public/mismatch');
        symlink($this->directory.'/missing', $this->directory.'/public/broken');
        symlink($this->directory.'/public-other', $this->directory.'/public/escape');
        symlink($this->directory.'/storage/app/private', $this->directory.'/public/storage/private');
        symlink($this->directory.'/storage/app/public-other', $this->directory.'/public/storage/sibling');
        symlink($this->directory.'/public', $this->directory.'/public/storage/public');
        symlink($this->directory.'/storage/app/private', $this->directory.'/public-other/shared');

        $this->app->usePublicPath($this->directory.'/public');
        $this->app->useStoragePath($this->directory.'/storage');
        $this->app['config']->set('filesystems.links', [
            $this->directory.'/public/storage' => $this->directory.'/storage/app/public',
            $this->directory.'/public/media' => $this->directory.'/media',
            $this->directory.'/public/storage/nested' => $this->directory.'/nested',
            $this->directory.'/public/mismatch' => $this->directory.'/media',
            $this->directory.'/public/broken' => $this->directory.'/missing',
            $this->directory.'/public-other/shared' => $this->directory.'/storage/app/private',
        ]);
    }


    protected function tearDown(): void
    {
        $this->server?->stop();
        (new Filesystem())->deleteDirectory($this->directory);

        parent::tearDown();
    }


    private function request(string $uri): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertIsResource($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $command = new Serve();
        $command->setLaravel($this->app);
        (new \ReflectionProperty($command, 'input'))->setValue($command, new ArrayInput([
            '--host' => '127.0.0.1',
            '--port' => substr($address, strrpos($address, ':') + 1),
        ], $command->getDefinition()));

        $this->server = new Process(
            (new \ReflectionMethod($command, 'serverCommand'))->invoke($command),
            public_path(),
            ['PHP_CLI_SERVER_WORKERS' => false],
        );
        $this->server->start();

        $socket = false;
        $deadline = microtime(true) + 5;

        do {
            $socket = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);

            if (!$socket) {
                usleep(10000);
            }
        } while (!$socket && $this->server->isRunning() && microtime(true) < $deadline);

        $this->assertIsResource($socket, $this->server->getErrorOutput());
        stream_set_timeout($socket, 5);
        fwrite($socket, "GET $uri HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n");
        $response = stream_get_contents($socket);
        fclose($socket);

        return $response;
    }
}
