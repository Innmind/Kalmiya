<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music\Format;

use Innmind\Kalmiya\Command\Music\Format;
use Innmind\CLI\Console;
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
};
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

final class Pretty implements Format
{
    private Transport $fulfill;

    public function __construct(Transport $fulfill)
    {
        $this->fulfill = $fulfill;
    }

    public function __invoke(
        Console $console,
        Library\Album|Catalog\Album $album,
        Artist $artist,
    ): Console {
        $console = $console->output(Str::of(
            "{$artist->name()->toString()} ||| {$album->name()->toString()}\n",
        ));
        $console = $this->printArtwork($console, $album);

        if ($album instanceof Library\Album) {
            return $console;
        }

        $url = $album
            ->url()
            ->withScheme(Scheme::of('itmss'))
            ->toString();

        return $console->output(Str::of("$url\n\n"));
    }

    private function printArtwork(
        Console $console,
        Library\Album|Catalog\Album $album,
    ): Console {
        if (!$console->interactive()) {
            return $console;
        }

        $inIterm = $console
            ->variables()
            ->get('LC_TERMINAL')
            ->filter(static fn($value) => $value === 'iTerm2')
            ->match(
                static fn() => true,
                static fn() => false,
            );

        if (!$inIterm) {
            return $console;
        }

        if ($album instanceof Library\Album) {
            $url = $album->artwork()->map(static fn($artwork) => $artwork->ofSize(
                Library\Album\Artwork\Width::of(300),
                Library\Album\Artwork\Height::of(300),
            ));
        } else {
            $url = $album->artwork()->ofSize(
                Catalog\Artwork\Width::of(300),
                Catalog\Artwork\Height::of(300),
            );
            $url = Maybe::just($url);
        }

        return $url
            ->map(static fn($url) => Request::of(
                $url,
                Method::get,
                ProtocolVersion::v11,
            ))
            ->flatMap(fn($request) => ($this->fulfill)($request)->maybe())
            ->map(static fn($success) => $success->response()->body())
            ->match(
                static fn($artwork) => $artwork
                    ->size()
                    ->match(
                        // @see https://www.iterm2.com/documentation-images.html
                        static fn($size) => $console
                            ->output(Str::of("\033]")) // OSC
                            ->output(Str::of('1337;File='))
                            ->output(Str::of("size={$size->toInt()}"))
                            ->output(Str::of(';width=300px;inline=1:'))
                            ->output(Str::of(\base64_encode($artwork->toString())))
                            ->output(Str::of(\chr(7))) // bell character
                            ->output(Str::of("\n")),
                        static fn() => $console,
                    ),
                static fn() => $console,
            );
    }
}
