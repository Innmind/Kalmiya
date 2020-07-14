<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\Command;

use Innmind\Kalmiya\Gene\GenerateSshKey;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Genome\{
    Genome,
    Gene,
    History\Event,
    Exception\PreConditionFailed,
    Exception\ExpressionFailed,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Output\Type,
};
use Innmind\Infrastructure\{
    Gene\CreateFolder,
    MacOS\Gene\Brew,
    MacOS\Gene\Archiver,
    MacOS\Gene\CleanMyMacX,
    MacOS\Gene\Dash,
    MacOS\Gene\Docker,
    MacOS\Gene\Flux,
    MacOS\Gene\GPG,
    MacOS\Gene\IStatMenus,
    MacOS\Gene\ITerm,
    MacOS\Gene\LittleSnitch,
    MacOS\Gene\Mackup,
    MacOS\Gene\MicroSnitch,
    MacOS\Gene\Paw,
    MacOS\Gene\Plex,
    MacOS\Gene\SublimeText,
    MacOS\Gene\TablePlus,
    MacOS\Gene\Transmission,
    MacOS\Gene\VimRC,
};
use Innmind\Immutable\Str;

final class Setup implements Command
{
    private Genome $genome;
    private OperatingSystem $os;

    public function __construct(Genome $genome, OperatingSystem $os)
    {
        $this->genome = $genome;
        $this->os = $os;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $this
            ->genome
            ->express($this->os)
            ->onStart(static function(Gene $gene) use ($env): void {
                $env->output()->write(Str::of("# Expressing {$gene->name()}...\n"));
            })
            ->onExpressed(static function(Gene $gene) use ($env): void {
                $env->output()->write(Str::of("# {$gene->name()} expressed!\n"));
            })
            ->onPreConditionFailed(static function(PreConditionFailed $e) use ($env): void {
                $env->error()->write(Str::of("Pre condition failure: {$e->getMessage()}\n"));
            })
            ->onExpressionFailed(static function(ExpressionFailed $e) use ($env): void {
                $env->error()->write(Str::of("Expression failure: {$e->getMessage()}\n"));
            })
            ->onCommand(
                static function(Server\Command $command) use ($env): void {
                    $env->output()->write(Str::of("> {$command->toString()}\n"));
                },
                static function(Str $chunk, Type $type) use ($env): void {
                    if ($type === Type::output()) {
                        $env->output()->write($chunk);
                    } else {
                        $env->error()->write($chunk);
                    }
                }
            )
            ->wait()
            ->foreach(static function(Event $event) use ($env): void {
                $env->output()->write(Str::of("Event: {$event->name()->toString()}...\n"));
            });
    }

    public function toString(): string
    {
        return <<<USAGE
        setup

        It will install all the applications and CLI tools usually used
        USAGE;
    }

    /**
     * This is not the ideal place to define the genome as it won't be easy to
     * replace on the fly but until a better option comes up it will stay here
     *
     * @internal
     */
    public static function genome(): Genome
    {
        return new Genome(
            // CLI tools
            Brew\Package::install('mackup'),
            Brew\Package::install('graphviz'),
            Brew\Package::install('tldr'),
            Brew\Package::install('bat'),
            Brew\Package::install('cloc'),
            Brew\Package::install('mas'),
            Gene\ComposerPackage::global('innmind/git-release'),
            Gene\ComposerPackage::global('innmind/lab-station'),
            Gene\ComposerPackage::global('innmind/dependency-graph'),
            Gene\ComposerPackage::global('symfony/var-dumper'),
            Gene\ComposerPackage::global('baptouuuu/series'),
            // Config
            new CreateFolder('Sites'),
            new CreateFolder('.series'),
            new CreateFolder('.kalmiya'),
            Mackup::useICloud(),
            Mackup::restore(),
            VimRC::syntaxOn(),
            new GenerateSshKey,
            // Apps
            Archiver::install(),
            CleanMyMacX::install(),
            Dash::install(),
            Docker::install(),
            Flux::install(),
            GPG::install('2020.1'),
            IStatMenus::install(),
            ITerm::install(),
            MicroSnitch::install(),
            Paw::install(),
            Plex::install('1.19.4.2935-79e214ead-x86_64'),
            SublimeText::install(),
            TablePlus::install(),
            Transmission::install('3.00'),
            LittleSnitch::install('4.5.2'),
        );
    }
}
