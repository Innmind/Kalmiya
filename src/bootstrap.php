<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\OperatingSystem\{
    Filesystem,
    Sockets,
};
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
use Innmind\Immutable\{
    Set,
    Map,
};
use Innmind\CLI;
use Innmind\HttpFramework;
use function Innmind\Templating\bootstrap as templating;

/**
 * @return list<CLI\Command>
 */
function cli(
    Filesystem $filesystem,
    Adapter $config,
    Transport $http,
    Clock $clock,
    Sockets $sockets,
    Server $server,
    IPC $ipc,
    Path $httpServer,
    Path $home,
    Path $backup
): array {
    $sdkFactory = new AppleMusic\SDKFactory($config, $http, $clock);
    $ipcServer = $ipc->listen(new Process\Name('apple-music'));
    /** @var Set<Path> */
    $backups = Set::of(
        Path::class,
        Path::of('Desktop/'),
        Path::of('Documents/'),
        Path::of('Downloads/'),
        Path::of('Movies/'),
    );
    /** @var Map<Path, Path> */
    $backups = $backups->toMapOf(
        Path::class,
        Path::class,
        static function(Path $folder) use ($home, $backup): \Generator {
            yield $home->resolve($folder) => $backup->resolve($folder);
        },
    );
    // it is not possible to automatically copy the iCloud folder as the files
    // are present on the filesystem but they are not downloaded so we would only
    // copy empty shells
    $foldersToOpen = Set::of(
        Path::class,
        $home->resolve(Path::of('Library/Mobile Documents/')),
        $backup,
    );

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
        new Command\Backup(
            $filesystem,
            $server->processes(),
            $backups,
            $foldersToOpen,
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
