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
    Message\Generic as Message,
};
use Innmind\Filesystem\{
    File,
    Name,
    Directory,
};
use Innmind\Http\{
    Response,
    Response\StatusCode,
};
use Innmind\Url\Path;
use Innmind\Validation\Is;
use Innmind\Immutable\{
    Map,
    Sequence,
    Set,
    Predicate\Instance,
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
            ->service(
                Services::templates,
                static fn($_, $os) => $os->filesystem()->mount(
                    Path::of('../templates/'),
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
            ))
            ->route(
                'GET /',
                static function($request, $_, $get) {
                    $sdk = $get(Services::appleMusic())();

                    return Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        $get(Services::templates())
                            ->get(Name::of('appleMusic.html'))
                            ->keep(Instance::of(File::class))
                            ->match(
                                static fn($file) => $file->content(),
                                static fn() => throw new \LogicException('Template not found'),
                            )
                            ->map(static fn($line) => $line->map(
                                static fn($str) => $str->replace('{{ token }}', $sdk->jwt()),
                            )),
                    );
                },
            )
            ->route(
                'POST /',
                static function($request, $_, $get, $os) {
                    $config = $get(Services::config());
                    $appleConfig = $config
                        ->get(Name::of('apple-music'))
                        ->keep(Instance::of(Directory::class))
                        ->match(
                            static fn($config) => $config,
                            static fn() => Directory::named('apple-music'),
                        );
                    $request
                        ->form()
                        ->get('token')
                        ->keep(Is::string()->asPredicate())
                        ->map(File\Content::ofString(...))
                        ->map(static fn($token) => File::named('user-token', $token))
                        ->map($appleConfig->add(...))
                        ->match(
                            $config->add(...),
                            static fn() => null,
                        );

                    $_ = $get(Services::ipc())
                        ->get(Process\Name::of('apple-music'))
                        ->flatMap(static fn($process) => $process->send(Sequence::of(
                            Message::of('text/plain', 'ok'),
                        )))
                        ->match(
                            static fn() => null,
                            static fn() => null,
                        );

                    return Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                    );
                },
            );
    }
}
