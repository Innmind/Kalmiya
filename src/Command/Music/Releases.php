<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use MusicCompanion\AppleMusic\{
    SDK,
    SDK\Catalog\Album,
    SDK\Catalog\Artwork\Width,
    SDK\Catalog\Artwork\Height,
    SDK\Catalog\Artist,
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
use Innmind\Http\{
    Message\Request\Request,
    Message\Method,
    ProtocolVersion,
};
use Innmind\Immutable\{
    Str,
    Set,
};
use function Innmind\Immutable\first;

final class Releases implements Command
{
    private SDK $sdk;
    private Adapter $config;
    private Clock $clock;
    private Transport $fulfill;

    public function __construct(SDK $sdk, Adapter $config, Clock $clock, Transport $fulfill)
    {
        $this->sdk = $sdk;
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

        $now = $this->clock->now();
        $userToken = $config->get(new Name('user-token'))->content()->toString();

        $library = $this->sdk->library($userToken);
        $catalog = $this->sdk->catalog($library->storefront()->id());
        $library
            ->artists()
            ->foreach(function($artist) use ($library, $catalog, $lastCheck, $env): void {
                $album = first($library->albums($artist->id()));
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
                    ->mapTo(
                        Album::class,
                        static fn(Album\Id $id): Album => $catalog->album($id),
                    )
                    ->filter(static fn(Album $catalog): bool => $catalog->name()->toString() === $album->name()->toString())
                    ->reduce(
                        Set::of(Artist\Id::class),
                        static fn(Set $ids, Album $album): Set => $ids->merge($album->artists()),
                    );
                /** @var Set<Artist> */
                $artists = $ids->reduce(
                    Set::of(Artist::class),
                    static function(Set $artists, Artist\Id $id) use ($catalog): Set {
                        try {
                            return ($artists)($catalog->artist($id));
                        } catch (ClientError $e) {
                            // the catalog doesn't seem consistent as ids
                            // provided by the api end up in 404
                            return $artists;
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
                    ->mapTo(
                        Album::class,
                        static fn(Album\Id $id): Album => $catalog->album($id),
                    )
                    ->filter(static fn(Album $album): bool => $album->release()->aheadOf($lastCheck))
                    ->foreach(function(Album $album) use ($artist, $env): void {
                        $env->output()->write(Str::of(
                            "{$artist->name()->toString()} ||| {$album->name()->toString()}\n"
                        ));
                        $this->printArtwork($env, $album);
                        $env->output()->write(Str::of(
                            "{$album->url()->toString()}\n\n"
                        ));
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
            music:releases

            Will list all the releases for artists in your library
            USAGE;
    }

    private function printArtwork(Environment $env, Album $album): void
    {
        if (!$album->hasArtwork()) {
            return;
        }

        if (!$env->interactive()) {
            return;
        }

        if (!$env->variables()->contains('LC_TERMINAL')) {
            return;
        }

        if ($env->variables()->get('LC_TERMINAL') !== 'iTerm2') {
            return;
        }

        $artwork = ($this->fulfill)(new Request(
            $album->artwork()->ofSize(new Width(500), new Height(500)),
            Method::get(),
            new ProtocolVersion(1, 1),
        ))->body();

        if (!$artwork->knowsSize()) {
            return;
        }

        $output = $env->output();
        // @see https://www.iterm2.com/documentation-images.html
        $output->write(Str::of("\033]")); // OSC
        $output->write(Str::of("1337;File="));
        $output->write(Str::of("size={$artwork->size()->toInt()}"));
        $output->write(Str::of(";inline=1:"));
        $output->write(Str::of(\base64_encode($artwork->toString())));
        $output->write(Str::of(\chr(7))); // bell character
        $output->write(Str::of("\n"));
    }
}
