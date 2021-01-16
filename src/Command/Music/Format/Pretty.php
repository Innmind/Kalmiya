<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music\Format;

use Innmind\Kalmiya\Command\Music\Format;
use Innmind\CLI\Environment;
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Message\Request\Request,
    Message\Method,
    ProtocolVersion,
};
use MusicCompanion\AppleMusic\SDK\{
    Library,
    Library\Artist,
    Catalog,
};
use Innmind\Url\Scheme;
use Innmind\Immutable\Str;

final class Pretty implements Format
{
    private Environment $env;
    private Transport $fulfill;

    public function __construct(Environment $env, Transport $fulfill)
    {
        $this->env = $env;
        $this->fulfill = $fulfill;
    }

    public function __invoke($album, Artist $artist): void
    {
        $this->env->output()->write(Str::of(
            "{$artist->name()->toString()} ||| {$album->name()->toString()}\n"
        ));
        $this->printArtwork($album);

        if ($album instanceof Library\Album) {
            return;
        }

        $url = $album
            ->url()
            ->withScheme(Scheme::of('itmss'))
            ->toString();
        $this->env->output()->write(Str::of("$url\n\n"));
    }

    /**
     * @param Library\Album|Catalog\Album $album
     */
    private function printArtwork($album): void
    {
        if (!$album->hasArtwork()) {
            return;
        }

        if (!$this->env->interactive()) {
            return;
        }

        if (!$this->env->variables()->contains('LC_TERMINAL')) {
            return;
        }

        if ($this->env->variables()->get('LC_TERMINAL') !== 'iTerm2') {
            return;
        }

        if ($album instanceof Library\Album) {
            $widthClass = Library\Album\Artwork\Width::class;
            $heightClass = Library\Album\Artwork\Height::class;
        } else {
            $widthClass = Catalog\Artwork\Width::class;
            $heightClass = Catalog\Artwork\Height::class;
        }

        try {
            /** @psalm-suppress PossiblyInvalidArgument */
            $artwork = ($this->fulfill)(new Request(
                $album->artwork()->ofSize(new $widthClass(300), new $heightClass(300)),
                Method::get(),
                new ProtocolVersion(1, 1),
            ))->body();
        } catch (\Exception $e) {
            // sometimes it fails with the message "Received HTTP/0.9 when not allowed"
            // discard such error for the moment
            return;
        }

        if (!$artwork->knowsSize()) {
            return;
        }

        $output = $this->env->output();
        // @see https://www.iterm2.com/documentation-images.html
        $output->write(Str::of("\033]")); // OSC
        $output->write(Str::of("1337;File="));
        $output->write(Str::of("size={$artwork->size()->toInt()}"));
        $output->write(Str::of(";width=300px;inline=1:"));
        $output->write(Str::of(\base64_encode($artwork->toString())));
        $output->write(Str::of(\chr(7))); // bell character
        $output->write(Str::of("\n"));
    }
}
