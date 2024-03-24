<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\AppleMusic;

use Innmind\Kalmiya\Exception\AppleMusicNotConfigured;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
    File,
};
use Innmind\HttpTransport\Transport;
use Innmind\TimeContinuum\Clock;
use Innmind\TimeContinuum\Earth\Period\Hour;
use Innmind\Immutable\{
    Maybe,
    Predicate\Instance,
};
use MusicCompanion\AppleMusic\{
    SDK,
    Key,
};

final class SDKFactory
{
    private Adapter $config;
    private Transport $http;
    private Clock $clock;

    public function __construct(
        Adapter $config,
        Transport $http,
        Clock $clock,
    ) {
        $this->config = $config;
        $this->http = $http;
        $this->clock = $clock;
    }

    /**
     * @throws AppleMusicNotConfigured
     */
    public function __invoke(): SDK
    {
        return $this
            ->config
            ->get(Name::of('apple-music'))
            ->keep(Instance::of(Directory::class))
            ->flatMap(
                static fn($appleMusic) => Maybe::all(
                    $appleMusic
                        ->get(Name::of('id'))
                        ->keep(Instance::of(File::class))
                        ->map(static fn($file) => $file->content()->toString())
                        ->map(\trim(...)),
                    $appleMusic
                        ->get(Name::of('team-id'))
                        ->keep(Instance::of(File::class))
                        ->map(static fn($file) => $file->content()->toString())
                        ->map(\trim(...)),
                    $appleMusic
                        ->get(Name::of('certificate'))
                        ->keep(Instance::of(File::class))
                        ->map(static fn($file) => $file->content()),
                )->map(Key::of(...)),
            )
            ->map(fn($key) => SDK::of(
                $this->clock,
                $this->http,
                $key,
                new Hour(24),
            ))
            ->match(
                static fn($sdk) => $sdk,
                static fn() => throw new AppleMusicNotConfigured,
            );
    }
}
