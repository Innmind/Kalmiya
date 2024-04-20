<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\Kalmiya\{
    AppleMusic\SDKFactory,
    Exception\AppleMusicNotConfigured,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
    File,
};
use Innmind\Immutable\Predicate\Instance;

final class Library implements Command
{
    private SDKFactory $makeSDK;
    private Adapter $config;

    public function __construct(SDKFactory $makeSDK, Adapter $config)
    {
        $this->makeSDK = $makeSDK;
        $this->config = $config;
    }

    public function __invoke(Console $console): Console
    {
        $sdk = ($this->makeSDK)();
        $config = $this
            ->config
            ->get(Name::of('apple-music'))
            ->keep(Instance::of(Directory::class))
            ->match(
                static fn($config) => $config,
                static fn() => throw new AppleMusicNotConfigured,
            );
        $wishedFormat = $console
            ->options()
            ->maybe('format')
            ->match(
                static fn($format) => $format,
                static fn() => 'text',
            );

        $format = match ($wishedFormat) {
            'markdown' => new Format\Markdown,
            default => new Format\Text,
        };

        $library = $config
            ->get(Name::of('user-token'))
            ->keep(Instance::of(File::class))
            ->map(static fn($file) => $file->content()->toString())
            ->flatMap($sdk->library(...))
            ->match(
                static fn($library) => $library,
                static fn() => throw new AppleMusicNotConfigured,
            );

        return $library
            ->artists()
            ->reduce(
                $console,
                static fn(Console $console, $artist) => $library
                    ->albums($artist->id())
                    ->reduce(
                        $console,
                        static fn(Console $console, $album) => $format(
                            $console,
                            $album,
                            $artist,
                        ),
                    ),
            );
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return <<<USAGE
            music:library --format=

            Will list all the music in your Apple Music library

            The format can either be 'text' or 'markdown'
            USAGE;
    }
}
