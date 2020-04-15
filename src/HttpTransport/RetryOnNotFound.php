<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\HttpTransport;

use Innmind\HttpTransport\Transport;
use Innmind\Http\Message\{
    Request,
    Response,
};
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\TimeContinuum\Earth\Period\Second;

final class RetryOnNotFound implements Transport
{
    private Transport $fulfill;
    private CurrentProcess $process;

    public function __construct(Transport $fulfill, CurrentProcess $process)
    {
        $this->fulfill = $fulfill;
        $this->process = $process;
    }

    public function __invoke(Request $request): Response
    {
        $i = 0;
        do {
            $response = ($this->fulfill)($request);

            if ($response->statusCode()->value() !== 404) {
                return $response;
            }

            $this->process->halt(new Second(10));
            ++$i;
        } while ($i < 5);

        return $response;
    }
}
