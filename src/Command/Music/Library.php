<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\Kalmiya\AppleMusic\SDKFactory;
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
    private SDKFactory $makeSDK;
    private Adapter $config;

    public function __construct(SDKFactory $makeSDK, Adapter $config)
    {
        $this->makeSDK = $makeSDK;
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

        $wishedFormat = $options->contains('format') ? $options->get('format') : 'text';

        switch ($wishedFormat) {
            case 'markdown':
                $format = new Format\Markdown($env);
                break;

            default:
                $format = new Format\Text($env);
                break;
        }

        $userToken = $config->get(new Name('user-token'))->content()->toString();

        $library = ($this->makeSDK)()->library($userToken);
        $library
            ->artists()
            ->foreach(static function($artist) use ($library, $format): void {
                $library
                    ->albums($artist->id())
                    ->foreach(static function($album) use ($format, $artist) {
                        $format($album, $artist);
                    });
            });
    }

    public function toString(): string
    {
        return <<<USAGE
            music:library --format=

            Will list all the music in your Apple Music library

            The format can either be 'text' or 'markdown'
            USAGE;
    }
}
