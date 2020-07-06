<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server;
use Innmind\Filesystem\{
    Adapter,
    File\File,
    Name,
};
use Innmind\Stream\Readable\Stream;
use Innmind\Url\Path;

final class NewProject implements Command
{
    private OperatingSystem $os;
    private Path $projects;
    private Path $backup;

    public function __construct(
        OperatingSystem $os,
        Path $projects,
        Path $backup
    ) {
        $this->os = $os;
        $this->projects = $projects;
        $this->backup = $backup;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $vendor = $arguments->get('vendor');
        $package = $arguments->get('package');
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
        $repository->add($template->get(new Name('.gitattributes')));
        $repository->add($template->get(new Name('.gitignore')));
        $repository->add($template->get(new Name('LICENSE')));
        $repository->add($template->get(new Name('phpunit.xml.dist')));
        $repository->add($template->get(new Name('psalm.xml')));
        $repository->add($this->template(
            $template,
            'composer.json',
            $vendor,
            $package,
        ));
        $repository->add($this->template(
            $template,
            'README.md',
            $vendor,
            $package,
        ));

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
    }

    public function toString(): string
    {
        return <<<USAGE
        new vendor package

        Will create a new composer project in your Sites folder
        USAGE;
    }

    private function template(
        Adapter $template,
        string $file,
        string $vendor,
        string $package
    ): File {
        $content = $template
            ->get(new Name($file))
            ->content()
            ->read()
            ->replace('{vendor}', $vendor)
            ->replace('{package}', $package)
            ->toString();

        return File::named($file, Stream::ofContent($content));
    }
}
