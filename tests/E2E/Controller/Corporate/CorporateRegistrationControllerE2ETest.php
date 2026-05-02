<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\Corporate;

use App\Entity\CorporateIdentity;
use App\Kernel;
use App\Service\Corporate\CorporateRegistrationService;
use App\Service\Crypters\CrypterService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class CorporateRegistrationControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testServiceIdentityExecutesControllerPipelineToResponse(): void
    {
        $client = static::createClient();

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with(self::callback(static fn (array $payload): bool => $payload['publicId'] === 'public-1' && $payload['scope'] === 'internal'))
            ->willReturn([
                'body' => 'encrypted-corporate-init',
                'headers' => ['X-Corporate' => 'init'],
            ]);
        static::getContainer()->set(CorporateRegistrationService::class, $corporateRegistrationService);

        $this->requestJsonSigned($client, 'POST', '/api/registration/corporate/identity/create/initialize', [
            'getIdentity' => [
                'publicId' => 'public-1',
                'scope' => 'internal',
                'businessModel' => 'businessBasic',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('encrypted-corporate-init', (string) $client->getResponse()->getContent());
        self::assertSame('init', $client->getResponse()->headers->get('X-Corporate'));
    }

    public function testServiceRegistrationExecutesControllerPipelineToSuccessMarker(): void
    {
        $client = static::createClient();

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('updateSubscriptionDataOrFail')
            ->with(self::callback(static fn (array $payload): bool => isset($payload['updateIdentity']['corporateId']) && $payload['updateIdentity']['corporateId'] === 'corp-1'))
            ->willReturn(new CorporateIdentity());
        static::getContainer()->set(CorporateRegistrationService::class, $corporateRegistrationService);

        $this->requestJsonSigned($client, 'POST', '/api/registration/corporate/identity/create/follow-up', [
            'updateIdentity' => [
                'corporateId' => 'corp-1',
                'callbackUserLogin' => 'https://example.test/login',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('1', (string) $client->getResponse()->getContent());
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