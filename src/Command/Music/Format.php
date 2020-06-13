<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command\Music;

use MusicCompanion\AppleMusic\SDK\{
    Library\Album as LibraryAlbum,
    Library\Artist,
    Catalog\Album as CatalogAlbum,
};

interface Format
{
    /**
     * @param LibraryAlbum|CatalogAlbum $album
     */
    public function __invoke($album, Artist $artist): void;
}
