<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\Business;

use App\DTO\Business\BusinessCreateRequestDTO;
use App\Kernel;
use App\Service\Corporate\CorporateRegistrationService;
use App\Service\Crypters\CrypterService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class BusinessControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testBusinessCreateExecutesControllerPipelineToResponse(): void
    {
        $client = static::createClient();

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getBusinessRegistration')
            ->with(self::callback(static function (array $payload): bool {
                $request = BusinessCreateRequestDTO::fromArray($payload);

                return $request->businessModel === 'businessBasic'
                    && $request->publicId === 'public-1'
                    && $request->scope === 'external';
            }))
            ->willReturn([
                'body' => 'encrypted-business-body',
                'headers' => ['X-Business' => 'created'],
            ]);
        static::getContainer()->set(CorporateRegistrationService::class, $corporateRegistrationService);

        $this->requestJsonSigned($client, 'POST', '/api/registration/corporate/business/create', [
            'business_create' => [
                'businessModel' => 'businessBasic',
                'publicId' => 'public-1',
                'scope' => 'external',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('encrypted-business-body', (string) $client->getResponse()->getContent());
        self::assertSame('created', $client->getResponse()->headers->get('X-Business'));
    }

    private function requestJsonSigned(KernelBrowser $client, string $method, string $uri, array $payload): void
    {
        $outerIv = 'outer-iv';
        /** @var CrypterService $crypterService */
        $crypterService = static::getContainer()->get(CrypterService::class);
        /** @var ParameterBagInterface $params */
        $params = static::getContainer()->get(ParameterBagInterface::class);

        $crypterService->setData($payload);
        $encryptedPayload = $crypterService->encryptData();
        $timestamp = time();
        $apiKey = (string) $params->get('SERVICE_API_KEY');
        $secret = (string) $params->get('SERVICE_API_SECRET');
        $signature = hash_hmac('sha256', $encryptedPayload . '|' . $outerIv, $secret);

        $client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => sprintf('HMAC %s:%s:%d', $apiKey, $signature, $timestamp),
            ],
            content: json_encode([
                'iv' => $outerIv,
                'zeroIntrusionProyApi' => $encryptedPayload,
            ], JSON_THROW_ON_ERROR),
        );
    }
}