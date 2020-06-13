<?php
declare(strict_types = 1);

require __DIR__.'/../vendor/autoload.php';

use function Innmind\Kalmiya\http;
use Innmind\HttpFramework\{
    Main,
    Application,
};
use Innmind\Url\Path;

new class extends Main {
    protected function configure(Application $app): Application
    {
        return $app
            ->configAt(Path::of(__DIR__.'/../config/'))
            ->handler(static fn($os, $env) => http(
                $os->filesystem()->mount(Path::of(
                    $env->get('HOME').'/.kalmiya/',
                )),
                $os->remote()->http(),
                $os->clock(),
                Path::of(__DIR__.'/../templates/'),
            ));
    }
};
