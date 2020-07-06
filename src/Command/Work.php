<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\Server;
use Innmind\Url\Path;

final class Work implements Command
{
    private Server $server;
    private Path $projects;

    public function __construct(Server $server, Path $projects)
    {
        $this->server = $server;
        $this->projects = $projects;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $vendor = 'innmind';

        if ($arguments->contains('vendor')) {
            $vendor = $arguments->get('vendor');
        }

        $package = $arguments->get('package');
        $path = $this->projects->resolve(Path::of("$vendor/$package/"))->toString();

        $open = new Server\Script(
            Server\Command::foreground('open')
                ->withShortOption('a', 'Sublime Text')
                ->withArgument($path),
            Server\Command::foreground('open')
                ->withShortOption('a', 'iTerm')
                ->withArgument($path),
        );
        $open($this->server);
    }

    public function toString(): string
    {
        return <<<USAGE
        work package [vendor]

        Open the apps to work on the given package

        Will open an iTerm window to "~/Sites/{vendor}/{package}/" and load
        the package in Sublime Text.

        By default the vendor is "innmind"
        USAGE;
    }
}
