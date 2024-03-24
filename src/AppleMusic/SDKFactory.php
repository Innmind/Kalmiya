<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\AppleMusic;

use Innmind\Kalmiya\Exception\AppleMusicNotConfigured;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
};
use Innmind\HttpTransport\Transport;
use Innmind\TimeContinuum\Clock;
use Innmind\TimeContinuum\Earth\Period\Hour;
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
        if (!$this->config->contains(new Name('apple-music'))) {
            throw new AppleMusicNotConfigured;
        }

        /** @var Directory */
        $appleMusic = $this->config->get(new Name('apple-music'));

        return new SDK\SDK(
            $this->clock,
            $this->http,
            new Key(
                \trim($appleMusic->get(new Name('id'))->content()->toString()),
                \trim($appleMusic->get(new Name('team-id'))->content()->toString()),
                $appleMusic
                    ->get(new Name('certificate'))
                    ->content(),
            ),
            new Hour(24),
        );
    }
}
