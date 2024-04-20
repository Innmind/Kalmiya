<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya;

use Innmind\Kalmiya\AppleMusic\SDKFactory;
use Innmind\DI\Service;
use Innmind\IPC\IPC;
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
    case config;
    case templates;
    case ipc;
    case appleMusic;

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
    public static function config(): self
    {
        /** @var self<Adapter> */
        return self::config;
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
     * @psalm-pure
     *
     * @return self<IPC>
     */
    public static function ipc(): self
    {
        /** @var self<IPC> */
        return self::ipc;
    }

    /**
     * @psalm-pure
     *
     * @return self<SDKFactory>
     */
    public static function appleMusic(): self
    {
        /** @var self<SDKFactory> */
        return self::appleMusic;
    }
}
