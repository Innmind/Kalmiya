<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\DependencyGraph\{
    Loader\VendorDependencies,
    Render,
    Vendor\Name,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use function Innmind\Immutable\unwrap;

final class Graph implements Command
{
    private VendorDependencies $load;
    private Render $render;
    private Server $server;
    private Path $workingDirectory;

    public function __construct(
        VendorDependencies $load,
        Render $render,
        Server $server,
        Path $workingDirectory
    ) {
        $this->load = $load;
        $this->render = $render;
        $this->server = $server;
        $this->workingDirectory = $workingDirectory;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $vendor = new Name('innmind');

        if ($arguments->contains('vendor')) {
            $vendor = new Name($arguments->get('vendor'));
        }

        $packages = ($this->load)($vendor);
        $fileName = "{$vendor->toString()}.svg";

        $graph = new Server\Script(
            Server\Command::foreground('dot')
                ->withShortOption('Tsvg')
                ->withShortOption('o', $fileName)
                ->withWorkingDirectory($this->workingDirectory)
                ->withInput(
                    ($this->render)(...unwrap($packages)),
                ),
            Server\Command::foreground('open')
                ->withArgument($fileName)
                ->withWorkingDirectory($this->workingDirectory)
        );
        $graph($this->server);
    }

    public function toString(): string
    {
        return <<<USAGE
        graph [vendor]

        Generate a graph of all packages of a vendor and their dependencies

        By default it will generate a graph for the "innmind" vendor
        USAGE;
    }
}
