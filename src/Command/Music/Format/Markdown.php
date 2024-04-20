<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music\Format;

use Innmind\Kalmiya\Command\Music\Format;
use Innmind\CLI\Console;
use MusicCompanion\AppleMusic\SDK\{
    Library,
    Library\Artist,
    Catalog,
};
use Innmind\Url\Scheme;
use Innmind\Immutable\{
    Str,
    Maybe,
};

final class Markdown implements Format
{
    private bool $headerPrinted = false;

    public function __invoke(
        Console $console,
        Library\Album|Catalog\Album $album,
        Artist $artist,
    ): Console {
        if (!$this->headerPrinted) {
            $console = $console
                ->output(Str::of("|Artist|Album|Artwork|\n"))
                ->output(Str::of("|---|---|---|\n"));
            $this->headerPrinted = true;
        }

        $albumName = $album->name()->toString();

        if ($album instanceof Library\Album) {
            $artwork = $album->artwork()->map(static fn($artwork) => $artwork->ofSize(
                Library\Album\Artwork\Width::of(200),
                Library\Album\Artwork\Height::of(200),
            ));
        } else {
            $artwork = $album->artwork()->ofSize(
                Catalog\Artwork\Width::of(200),
                Catalog\Artwork\Height::of(200),
            );
            $artwork = Maybe::just($artwork);
        }

        $artwork = $artwork->match(
            static fn($url) => "![]({$url->toString()})",
            static fn() => '',
        );

        if ($album instanceof Catalog\Album) {
            $url = $album
                ->url()
                ->withScheme(Scheme::of('itmss'))
                ->toString();
            $albumName = "[$albumName]($url)";
        }

        return $console->output(
            Str::of("| {$artist->name()->toString()} | $albumName | $artwork |\n"),
        );
    }
}
