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
use MusicCompanion\AppleMusic\SDK\Catalog\Album;
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
    File,
};
use Innmind\TimeContinuum\{
    Clock,
    Earth\Period\Year,
    Earth\Format\ISO8601,
};
use Innmind\HttpTransport\Transport;
use Innmind\Immutable\Predicate\Instance;

final class Releases implements Command
{
    private SDKFactory $makeSDK;
    private Adapter $config;
    private Clock $clock;
    private Transport $fulfill;

    public function __construct(
        SDKFactory $makeSDK,
        Adapter $config,
        Clock $clock,
        Transport $fulfill,
    ) {
        $this->makeSDK = $makeSDK;
        $this->config = $config;
        $this->clock = $clock;
        $this->fulfill = $fulfill;
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

        $lastCheck = $config
            ->get(Name::of('releases-check'))
            ->keep(Instance::of(File::class))
            ->map(static fn($file) => $file->content()->toString())
            ->flatMap(fn($lastCheck) => $this->clock->at($lastCheck, new ISO8601))
            ->match(
                static fn($lastCheck) => $lastCheck,
                fn() => $this->clock->now()->goBack(Year::of(1)),
            );

        $wishedFormat = $console
            ->options()
            ->maybe('format')
            ->match(
                static fn($format) => $format,
                static fn() => null,
            );

        $format = match ($wishedFormat) {
            'pretty' => new Format\Pretty($this->fulfill),
            'markdown' => new Format\Markdown,
            default => new Format\Text,
        };

        $now = $this->clock->now();
        $library = $config
            ->get(Name::of('user-token'))
            ->keep(Instance::of(File::class))
            ->map(static fn($file) => $file->content()->toString())
            ->flatMap($sdk->library(...))
            ->match(
                static fn($library) => $library,
                static fn() => throw new AppleMusicNotConfigured,
            );

        $catalog = $sdk->catalog($library->storefront()->id());
        $console = $library
            ->artists()
            ->reduce(
                $console,
                static function(Console $console, $artist) use ($library, $catalog, $lastCheck, $now, $format) {
                    $album = $library
                        ->albums($artist->id())
                        ->first()
                        ->match(
                            static fn($album) => $album,
                            static fn() => null,
                        );

                    if (\is_null($album)) {
                        return $console;
                    }

                    $artistName = $artist->name()->toString();
                    $albumName = $album->name()->toString();
                    // add an album name to the search to narrow the list of returned
                    // artists to make sure we get the right one
                    $term = "$artistName $albumName";

                    if (\strtolower($artistName) === \strtolower($albumName)) {
                        // this case happens for America or HAERTS for example, and
                        // if the same term is in the research twice it will not find
                        // the album, and the results will contain WAY too many albums
                        $term = $artistName;
                    }

                    $search = $catalog->search($term);

                    $ids = $search
                        ->albums()
                        ->take(25)
                        ->flatMap(
                            static fn($id) => $catalog
                                ->album($id)
                                ->toSequence(),
                        )
                        ->filter(static fn(Album $catalog): bool => $catalog->name()->toString() === $album->name()->toString())
                        ->toSet()
                        ->flatMap(static fn($album) => $album->artists());
                    $catalogArtist = $ids
                        ->flatMap(
                            static fn($id) => $catalog
                                ->artist($id)
                                ->toSequence()
                                ->toSet(),
                        )
                        ->filter(
                            static fn($catalog) => $catalog->name()->toString() === $artist->name()->toString(),
                        )
                        ->find(static fn() => true)
                        ->match(
                            static fn($artist) => $artist,
                            static fn() => null,
                        );

                    if (\is_null($catalogArtist)) {
                        return $console;
                    }

                    $albums = $catalogArtist->albums();

                    return $albums
                        ->flatMap(
                            static fn($id) => $catalog
                                ->album($id)
                                ->toSequence()
                                ->toSet(),
                        )
                        ->filter(static fn($album) => $album->release()->match(
                            static fn($release) => $release->aheadOf($lastCheck),
                            static fn() => false,
                        ))
                        ->filter(static fn($album) => $album->release()->match(
                            $now->aheadOf(...), // do not display future releases
                            static fn() => false,
                        ))
                        ->reduce(
                            $console,
                            static fn(Console $console, $album) => $format(
                                $console,
                                $album,
                                $artist,
                            ),
                        );
                },
            );

        $this->config->add(
            $config->add(File::named(
                'releases-check',
                File\Content::ofString($now->format(new ISO8601)),
            )),
        );

        return $console;
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return <<<USAGE
            music:releases --format=

            Will list all the releases for artists in your library

            The format can be either 'text', 'markdown' or 'pretty'
            USAGE;
    }
}
