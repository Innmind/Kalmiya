<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\File;
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Map,
    Set,
    Str,
};
use function Innmind\Immutable\{
    assertMap,
    assertSet,
};

final class Backup implements Command
{
    private Filesystem $filesystem;
    private Server\Processes $processes;
    /** @var Map<Path, Path> */
    private Map $backups;
    /** @var Set<Path> */
    private Set $foldersToOpen;

    /**
     * @param Map<Path, Path> $backups
     * @param Set<Path> $foldersToOpen
     */
    public function __construct(
        Filesystem $filesystem,
        Server\Processes $processes,
        Map $backups,
        Set $foldersToOpen
    ) {
        assertMap(Path::class, Path::class, $backups, 2);
        assertSet(Path::class, $foldersToOpen, 3);

        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->backups = $backups;
        $this->foldersToOpen = $foldersToOpen;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $this->backups->foreach(function(Path $source, Path $target) use ($env): void {
            if (!$this->filesystem->contains($source)) {
                $env->output()->write(Str::of("Backup source {$source->toString()} not accessible\n"));

                return;
            }

            if (!$this->filesystem->contains($target)) {
                $env->output()->write(Str::of("Backup target {$target->toString()} not accessible\n"));

                return;
            }

            $env->output()->write(Str::of("Backuping {$source->toString()} to {$target->toString()}:\n"));
            $target = $this->filesystem->mount($target);
            $this
                ->filesystem
                ->mount($source)
                ->all()
                ->foreach(static function(File $file) use ($env, $target): void {
                    $env->output()->write(Str::of("{$file->name()->toString()}..."));
                    $target->add($file);
                    $env->output()->write(Str::of(" OK\n"));
                });
        });
        $this->foldersToOpen->foreach(function(Path $folder): void {
            $this
                ->processes
                ->execute(
                    Server\Command::foreground('open')
                        ->withArgument($folder->toString()),
                )
                ->wait();
        });
    }

    public function toString(): string
    {
        return 'backup';
    }
}
