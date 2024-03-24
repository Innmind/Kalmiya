<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Filesystem\Adapter;
use Innmind\HttpTransport\Transport;
use Innmind\TimeContinuum\Clock;
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
use Innmind\DependencyGraph\{
    Render,
    Loader,
};
use function Innmind\Templating\bootstrap as templating;

/**
 * @return list<CLI\Command>
 */
function cli(
    OperatingSystem $os,
    Adapter $config,
    IPC $ipc,
    Path $httpServer,
    Path $home,
    Path $backup,
    Path $codeBackup
): array {
    $http = new HttpTransport\RetryOnNotFound(
        $os->remote()->http(),
        $os->process(),
    );

    $sdkFactory = new AppleMusic\SDKFactory($config, $http, $os->clock());
    $ipcServer = $ipc->listen(new Process\Name('apple-music'));
    $backups = Set::of(
        Path::class,
        Path::of('Desktop/'),
        Path::of('Documents/'),
        Path::of('Downloads/'),
        Path::of('Movies/'),
        Path::of('Library/Services/'),
        Path::of('.series/'),
        Path::of('.kalmiya/'),
    );
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

    $projects = $home->resolve(Path::of('Sites/'));

    $package = new Loader\Package($http);
    $vendor = new Loader\Vendor($http, $package);

    return [
        new Command\Music\Authenticate(
            new Command\Music\Library($sdkFactory, $config),
            $config,
            $os->sockets(),
            $os->control()->processes(),
            $ipcServer,
            $httpServer,
        ),
        new Command\Music\Authenticate(
            new Command\Music\Releases($sdkFactory, $config, $os->clock(), $http),
            $config,
            $os->sockets(),
            $os->control()->processes(),
            $ipcServer,
            $httpServer,
        ),
        new Command\Backup(
            $os->filesystem(),
            $os->control()->processes(),
            $backups,
            $foldersToOpen,
        ),
        new Command\Restore(
            $os->filesystem(),
            $backups,
        ),
        new Command\Setup(
            Command\Setup::genome(),
            $os,
        ),
        new Command\Graph(
            new Loader\VendorDependencies($vendor, $package),
            new Render,
            $os->control(),
            $os->status()->tmp(),
        ),
        new Command\NewProject(
            $os,
            $projects,
            $codeBackup,
        ),
        new Command\Work(
            $os->control(),
            $projects,
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
