<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\Service;
use Innmind\Filesystem\Adapter;
use Innmind\Url\Path;
use Innmind\Immutable\Map;

/**
 * @psalm-immutable
 * @template S of object
 * @implements Service<S>
 */
enum Services implements Service
{
    case home;
    case backup;
    case backups;
    case templates;
    case os;

    /**
     * @psalm-pure
     *
     * @return self<Path>
     */
    public static function home(): self
    {
        /** @var self<Path> */
        return self::home;
    }

    /**
     * @psalm-pure
     *
     * @return self<Path>
     */
    public static function backup(): self
    {
        /** @var self<Path> */
        return self::backup;
    }

    /**
     * @psalm-pure
     *
     * @return self<Map<Path, Path>>
     */
    public static function backups(): self
    {
        /** @var self<Map<Path, Path>> */
        return self::backups;
    }

    /**
     * @psalm-pure
     *
     * @return self<Adapter>
     */
    public static function templates(): self
    {
        /** @var self<Adapter> */
        return self::templates;
    }

    /**
     * @internal
     * @psalm-pure
     *
     * @return self<OperatingSystem>
     */
    public static function os(): self
    {
        /** @var self<OperatingSystem> */
        return self::os;
    }
}
