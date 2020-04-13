<?php
declare(strict_types = 1);

namespace Innmind\Kalmiya\HttpTransport;

use Innmind\HttpTransport\{
    Transport,
    Exception\ClientError,
};
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
            try {
                return ($this->fulfill)($request);
            } catch (ClientError $e) {
                if ($e->response()->statusCode()->value() !== 404) {
                    throw $e;
                }

                $this->process->halt(new Second(1));
                ++$i;
            }
        } while ($i < 5);

        throw $e;
    }
}
