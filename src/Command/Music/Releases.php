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
use MusicCompanion\AppleMusic\SDK\Catalog\{
    Album,
    Artwork\Width,
    Artwork\Height,
    Artist,
};
use Innmind\Filesystem\{
    Adapter,
    Name,
    Directory,
    File\File,
};
use Innmind\Stream\Readable\Stream;
use Innmind\TimeContinuum\{
    Clock,
    Earth\Period\Year,
    Earth\Format\ISO8601,
};
use Innmind\HttpTransport\{
    Transport,
    Exception\ClientError,
};
use Innmind\Immutable\{
    Str,
    Set,
};
use function Innmind\Immutable\first;

final class Releases implements Command
{
    private SDKFactory $makeSDK;
    private Adapter $config;
    private Clock $clock;
    private Transport $fulfill;

    public function __construct(SDKFactory $makeSDK, Adapter $config, Clock $clock, Transport $fulfill)
    {
        $this->makeSDK = $makeSDK;
        $this->config = $config;
        $this->clock = $clock;
        $this->fulfill = $fulfill;
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

        if (!$config->contains(new Name('releases-check'))) {
            $lastCheck = $this->clock->now()->goBack(new Year(1));
        } else {
            $lastCheck = $this->clock->at(
                $config->get(new Name('releases-check'))->content()->toString(),
                new ISO8601,
            );
        }

        $wishedFormat = $options->contains('format') ? $options->get('format') : 'text';

        switch ($wishedFormat) {
            case 'pretty':
                $format = new Format\Pretty($env, $this->fulfill);
                break;

            case 'markdown':
                $format = new Format\Markdown($env);
                break;

            default:
                $format = new Format\Text($env);
                break;
        }

        $now = $this->clock->now();
        $userToken = $config->get(new Name('user-token'))->content()->toString();

        $sdk = ($this->makeSDK)();
        $library = $sdk->library($userToken);
        $catalog = $sdk->catalog($library->storefront()->id());
        $library
            ->artists()
            ->foreach(static function($artist) use ($library, $catalog, $lastCheck, $now, $format): void {
                try {
                    $album = first($library->albums($artist->id()));
                } catch (ClientError $e) {
                    return;
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

                /** @var Set<Artist\Id> */
                $ids = $search
                    ->albums()
                    ->take(25)
                    ->toSequenceOf(
                        Album::class,
                        static function(Album\Id $id) use ($catalog): \Generator {
                            try {
                                yield $catalog->album($id);
                            } catch (ClientError $e) {
                                return;
                            }
                        },
                    )
                    ->filter(static fn(Album $catalog): bool => $catalog->name()->toString() === $album->name()->toString())
                    ->reduce(
                        Set::of(Artist\Id::class),
                        static fn(Set $ids, Album $album): Set => $ids->merge($album->artists()),
                    );
                /** @var Set<Artist> */
                $artists = $ids->toSetOf(
                    Artist::class,
                    static function(Artist\Id $id) use ($catalog): \Generator {
                        try {
                            yield $catalog->artist($id);
                        } catch (ClientError $e) {
                            // the catalog doesn't seem consistent as ids
                            // provided by the api end up in 404
                            return;
                        }
                    },
                );
                $artists = $artists->filter(
                    static fn(Artist $catalog): bool => $catalog->name()->toString() === $artist->name()->toString(),
                );

                if ($artists->empty()) {
                    return;
                }

                $catalog
                    ->artist(first($artists)->id())
                    ->albums()
                    ->toSetOf(
                        Album::class,
                        static function(Album\Id $id) use ($catalog): \Generator {
                            try {
                                yield $catalog->album($id);
                            } catch (ClientError $e) {
                                return;
                            }
                        },
                    )
                    ->filter(static fn(Album $album): bool => $album->release()->aheadOf($lastCheck))
                    ->filter(static fn(Album $album): bool => $now->aheadOf($album->release())) // do not display future releases
                    ->foreach(static function(Album $album) use ($artist, $format): void {
                        $format($album, $artist);
                    });
            });

        $this->config->add(
            $config->add(File::named(
                'releases-check',
                Stream::ofContent($now->format(new ISO8601)),
            )),
        );
    }

    public function toString(): string
    {
        return <<<USAGE
            music:releases --format=

            Will list all the releases for artists in your library

            The format can be either 'text', 'markdown' or 'pretty'
            USAGE;
    }
}
