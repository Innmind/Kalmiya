<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\OperatingSystem\Sockets;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};
use Innmind\HttpTransport\Transport;
use Innmind\TimeContinuum\Clock;
use Innmind\Server\Control\Server;
use Innmind\IPC\{
    IPC,
    Process,
};
use Innmind\Url\Path;
use Innmind\CLI;
use Innmind\HttpFramework;
use function Innmind\Templating\bootstrap as templating;

/**
 * @return list<CLI\Command>
 */
function cli(
    Adapter $config,
    Transport $http,
    Clock $clock,
    Sockets $sockets,
    Server $server,
    IPC $ipc,
    Path $httpServer
): array {
    $sdkFactory = new AppleMusic\SDKFactory($config, $http, $clock);
    $ipcServer = $ipc->listen(new Process\Name('apple-music'));

    return [
        new Command\Music\Authenticate(
            new Command\Music\Library($sdkFactory, $config),
            $config,
            $sockets,
            $server->processes(),
            $ipcServer,
            $httpServer,
        ),
        new Command\Music\Authenticate(
            new Command\Music\Releases($sdkFactory, $config, $clock, $http),
            $config,
            $sockets,
            $server->processes(),
            $ipcServer,
            $httpServer,
        ),
    ];
}

function http(
    Adapter $config,
    Transport $http,
    Clock $clock,
    Path $templates,
    IPC $ipc
): HttpFramework\RequestHandler {
    return new RequestHandler\AppleMusic(
        new AppleMusic\SDKFactory($config, $http, $clock),
        templating($templates),
        $config,
        $ipc,
        new Process\Name('apple-music'),
    );
}
