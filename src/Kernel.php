<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\Kalmiya\{
    AppleMusic\SDKFactory,
};
use Innmind\Framework\{
    Application,
    Middleware,
};
use Innmind\OperatingSystem\OperatingSystem\Resilient;
use Innmind\IPC\{
    Factory as IPC,
    Process,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Sequence,
    Set,
};

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->mapOperatingSystem(Resilient::of(...))
            ->service(
                Services::home,
                static fn($_, $__, $env) => $env
                    ->maybe('HOME')
                    ->map(static fn($home) => $home.'/')
                    ->map(Path::of(...))
                    ->match(
                        static fn($home) => $home,
                        static fn() => throw new \RuntimeException('HOME not found'),
                    ),
            )
            ->service(
                Services::backup,
                static fn($_, $__, $env) => Path::of('/Volumes/Backup/'.$env->get('USER').'/'),
            )
            ->service(
                Services::backups,
                static fn($get) => Map::of(
                    ...Sequence::of(
                        'Desktop/',
                        'Documents/',
                        'Downloads/',
                        'Movies/',
                        'Library/Services/',
                        '.series/',
                        '.kalmiya/',
                    )
                        ->map(Path::of(...))
                        ->map(static fn($path) => [
                            $get(Services::home())->resolve($path),
                            $get(Services::backup())->resolve($path),
                        ])
                        ->toList(),
                ),
            )
            ->service(
                Services::config,
                static fn($get, $os) => $os->filesystem()->mount(
                    $get(Services::home())->resolve(Path::of('.kalmyia/')),
                ),
            )
            ->service(Services::ipc, static fn($_, $os) => IPC::build($os))
            ->service(
                Services::appleMusic,
                static fn($get, $os) => new SDKFactory(
                    $get(Services::config()),
                    $os->remote()->http(),
                    $os->clock(),
                ),
            )
            ->command(static fn($get, $os) => new Command\Music\Authenticate(
                new Command\Music\Library(
                    $get(Services::appleMusic()),
                    $get(Services::config()),
                ),
                $get(Services::config()),
                $os->sockets(),
                $os->control()->processes(),
                $get(Services::ipc())->listen(Process\Name::of('apple-music')),
                Path::of(__DIR__.'/../http/'),
            ))
            ->command(static fn($get, $os) => new Command\Music\Authenticate(
                new Command\Music\Releases(
                    $get(Services::appleMusic()),
                    $get(Services::config()),
                    $os->clock(),
                    $os->remote()->http(),
                ),
                $get(Services::config()),
                $os->sockets(),
                $os->control()->processes(),
                $get(Services::ipc())->listen(Process\Name::of('apple-music')),
                Path::of(__DIR__.'/../http/'),
            ))
            ->command(static fn($get, $os) => new Command\Backup(
                $os->filesystem(),
                $os->control()->processes(),
                $get(Services::backups()),
                Set::of(
                    $get(Services::home())->resolve(Path::of('Library/Mobile Documents/')),
                    $get(Services::backup()),
                ),
            ))
            ->command(static fn($get, $os) => new Command\Restore(
                $os->filesystem(),
                $get(Services::backups()),
            ))
            ->command(static fn($get, $os) => new Command\NewProject(
                $os,
                $get(Services::home())->resolve(Path::of('Sites/')),
                Path::of('/Volumes/Backup/Code/'),
            ));
    }
}
