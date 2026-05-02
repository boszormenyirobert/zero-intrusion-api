<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\Account;

use App\DTO\Account\AccountRequestDTO;
use App\DTO\Account\AccountResponseDTO;
use App\Kernel;
use App\Service\Account\AccountLookupService;
use App\Service\Crypters\CrypterService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class AccountControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAccountExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (AccountRequestDTO $request): bool {
                return $request->publicId === 'public-1'
                    && $request->email === 'user@example.test';
            }))
            ->willReturn(new AccountResponseDTO([
                ['corporateId' => 'corp-1'],
            ], [
                'id' => 9,
                'pro' => true,
            ]));
        static::getContainer()->set(AccountLookupService::class, $lookupService);

        $this->requestJsonSigned($client, 'POST', '/api/account/all', [
            'get_registrated_business' => [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'accounts' => [
                ['corporateId' => 'corp-1'],
            ],
            'businessSubscription' => [
                'id' => 9,
                'pro' => true,
            ],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
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