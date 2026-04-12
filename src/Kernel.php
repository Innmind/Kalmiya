<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\Framework\{
    Application,
    Middleware,
};
use Innmind\OperatingSystem\Config\Resilient;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Sequence,
    Set,
};

final class Kernel implements Middleware
{
    #[\Override]
    public function __invoke(Application $app): Application
    {
        return $app
            ->mapOperatingSystem(static fn($os) => $os->map(Resilient::new()))
            ->service(Services::os, static fn($_, $os) => $os)
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
                Services::templates,
                static fn($_, $os) => $os
                    ->filesystem()
                    ->mount(Path::of('../templates/'))
                    ->unwrap(),
            )
            ->command(static fn($get) => new Command\Backup(
                $get(Services::os())->filesystem(),
                $get(Services::os())->control()->processes(),
                $get(Services::backups()),
                Set::of(
                    $get(Services::home())->resolve(Path::of('Library/Mobile Documents/')),
                    $get(Services::backup()),
                ),
            ))
            ->command(static fn($get) => new Command\Restore(
                $get(Services::os())->filesystem(),
                $get(Services::backups()),
            ))
            ->command(static fn($get) => new Command\NewProject(
                $get(Services::os()),
                $get(Services::home())->resolve(Path::of('Sites/')),
                Path::of('/Volumes/Backup/Code/'),
            ));
    }
}
