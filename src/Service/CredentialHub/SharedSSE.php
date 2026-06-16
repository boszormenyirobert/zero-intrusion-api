<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\Payload\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\Cache\ProcessStateCacheService;
use Psr\Log\LoggerInterface;

class SharedSSE
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly AccessRegistryRegistrationService $accessRegistryRegistrationService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(string $key): StreamedResponse
    {
        return new StreamedResponse(function () use ($key) {

            session_write_close();

            $startedAt = time();
            
            while (true) {                
                if ((time() - $startedAt) >= 15) {

                    echo "event: timeout\n";
                    echo 'data: ' . json_encode([
                        'success' => false,
                    ]) . "\n\n";

                    @ob_flush();
                    flush();

                    break;
                }
                
                $value = $this->processStateCacheService->get($key);
                
                if ($value !== null && $value !== false) {

                    echo "event: found\n";
                    echo 'data: ' . \json_encode($value)     . "\n\n";

                    @ob_flush();
                    flush();

                    break;
                }

                @ob_flush();
                flush();

                sleep(1);
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}