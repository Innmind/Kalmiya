<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\Kalmiya\{
    AppleMusic\SDKFactory,
    Exception\AppleMusicNotConfigured,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};

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
        $sdk = ($this->makeSDK)();
        /** @var Directory */
        $config = $this->config->get(new Name('apple-music'));
        $wishedFormat = $options->contains('format') ? $options->get('format') : 'text';

        switch ($wishedFormat) {
            case 'markdown':
                $format = new Format\Markdown($env);
                break;

            default:
                $format = new Format\Text($env);
                break;
        }

        if (!$config->contains(new Name('user-token'))) {
            throw new AppleMusicNotConfigured;
        }

        $userToken = $config->get(new Name('user-token'))->content()->toString();

        $library = $sdk->library($userToken);
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
