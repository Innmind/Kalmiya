<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use MusicCompanion\AppleMusic\SDK;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};
use Innmind\Immutable\Str;

final class Library implements Command
{
    private SDK $sdk;
    private Adapter $config;

    public function __construct(SDK $sdk, Adapter $config)
    {
        $this->sdk = $sdk;
        $this->config = $config;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        if (!$this->config->contains(new Name('apple-music'))) {
            $env->error()->write(Str::of("No config provided\n"));
            $env->exit(1);

            return;
        }

        $config = $this->config->get(new Name('apple-music'));

        if (!$config instanceof Directory || !$config->contains(new Name('user-token'))) {
            $env->error()->write(Str::of("No config provided\n"));
            $env->exit(1);

            return;
        }

        $userToken = $config->get(new Name('user-token'))->content()->toString();

        $library = $this->sdk->library($userToken);
        $library
            ->artists()
            ->foreach(static function($artist) use ($library, $env): void {
                $library
                    ->albums($artist->id())
                    ->foreach(static function($album) use ($env, $artist) {
                        $env
                            ->output()
                            ->write(Str::of("{$artist->name()->toString()} ||| {$album->name()->toString()}\n"));
                    });
            });
    }

    public function toString(): string
    {
        return <<<USAGE
            music:library

            Will list all the music in your Apple Music library
            USAGE;
    }
}