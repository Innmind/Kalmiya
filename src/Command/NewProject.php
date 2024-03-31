<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server;
use Innmind\Filesystem\{
    File,
    Name,
};
use Innmind\Url\Path;

final class NewProject implements Command
{
    private OperatingSystem $os;
    private Path $projects;
    private Path $backup;

    public function __construct(
        OperatingSystem $os,
        Path $projects,
        Path $backup,
    ) {
        $this->os = $os;
        $this->projects = $projects;
        $this->backup = $backup;
    }

    public function __invoke(Console $console): Console
    {
        $vendor = $console->arguments()->get('vendor');
        $package = $console->arguments()->get('package');
        $projectPath = $this->projects->resolve(Path::of("$vendor/$package/"));
        $backupPath = $this->backup->resolve(Path::of("$vendor/$package/"));
        $commands = [
            Server\Command::foreground('mkdir')
                ->withShortOption('p')
                ->withArgument("$vendor/$package")
                ->withWorkingDirectory($this->projects),
            Server\Command::foreground('git')
                ->withArgument('init')
                ->withWorkingDirectory($projectPath),
            Server\Command::foreground('git')
                ->withArgument('remote')
                ->withArgument('add')
                ->withArgument('origin')
                ->withArgument("git@github.com:$vendor/$package.git")
                ->withWorkingDirectory($projectPath),
        ];

        if ($this->os->filesystem()->contains($this->backup)) {
            $commands[] = Server\Command::foreground('mkdir')
                ->withShortOption('p')
                ->withArgument("$vendor/$package")
                ->withWorkingDirectory($this->backup);
            $commands[] = Server\Command::foreground('git')
                ->withArgument('init')
                ->withOption('bare')
                ->withWorkingDirectory($backupPath);
            $commands[] = Server\Command::foreground('git')
                ->withArgument('remote')
                ->withArgument('set-url')
                ->withOption('add')
                ->withArgument('origin')
                ->withArgument($backupPath->toString())
                ->withWorkingDirectory($projectPath);
        }

        $init = new Server\Script(...$commands);
        $init($this->os->control());

        $repository = $this->os->filesystem()->mount($projectPath);
        $template = $this->os->filesystem()->mount(Path::of(__DIR__.'/../../project-template/'));
        $_ = $template
            ->root()
            ->all()
            ->map(static fn($file) => match (true) {
                $file instanceof File => self::update($file, $vendor, $package),
                default => $file,
            })
            ->foreach($repository->add(...));

        $finish = new Server\Script(
            Server\Command::foreground('git')
                ->withArgument('add')
                ->withArgument('.')
                ->withWorkingDirectory($projectPath),
            Server\Command::foreground('git')
                ->withArgument('commit')
                ->withShortOption('m', 'initial commit')
                ->withWorkingDirectory($projectPath),
            Server\Command::foreground('git')
                ->withArgument('checkout')
                ->withShortOption('b', 'develop')
                ->withWorkingDirectory($projectPath),
            Server\Command::foreground('composer')
                ->withArgument('install')
                ->withWorkingDirectory($projectPath),
            Server\Command::foreground('open')
                ->withArgument("https://github.com/organizations/$vendor/repositories/new"),
            Server\Command::foreground('open')
                ->withShortOption('a', 'Sublime Text')
                ->withArgument($projectPath->toString()),
            Server\Command::foreground('open')
                ->withShortOption('a', 'iTerm')
                ->withArgument($projectPath->toString()),
        );
        $finish($this->os->control());

        return $console;
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return <<<USAGE
        new vendor package

        Will create a new composer project in your Sites folder
        USAGE;
    }

    public static function update(
        File $file,
        string $vendor,
        string $package,
    ): File {
        if ($file->name()->str()->startsWith('dot-')) {
            /** @psalm-suppress ArgumentTypeCoercion */
            return $file->rename(Name::of(
                $file
                    ->name()
                    ->str()
                    ->drop(4)
                    ->prepend('.')
                    ->toString(),
            ));
        }

        if (\in_array($file->name()->toString(), ['composer.json', 'README.md'], true)) {
            return $file->withContent(
                $file->content()->map(
                    static fn($line) => $line->map(
                        static fn($str) => $str
                            ->replace('{vendor}', $vendor)
                            ->replace('{package}', $package),
                    ),
                ),
            );
        }

        return $file;
    }
}
