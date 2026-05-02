<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Account;

use App\DTO\Account\AccountRequestDTO;
use App\DTO\Account\AccountResponseDTO;
use App\Kernel;
use App\Service\Account\AccountLookupService;
use App\Service\Account\AccountRequestMapper;
use App\Service\Account\AccountRequestResolver;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAccountRouteReturnsResolvedAccountPayload(): void
    {
        $client = static::createClient();

        $requestResolver = $this->createMock(AccountRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn([
                'get_registrated_business' => [
                    'publicId' => 'public-1',
                    'email' => 'user@example.test',
                ],
            ]);
        static::getContainer()->set(AccountRequestResolver::class, $requestResolver);

        $requestMapper = $this->createMock(AccountRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new AccountRequestDTO('public-1', 'user@example.test'));
        static::getContainer()->set(AccountRequestMapper::class, $requestMapper);

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new AccountResponseDTO([
                ['corporateId' => 'corp-1'],
            ], [
                'id' => 9,
                'pro' => true,
            ]));
        static::getContainer()->set(AccountLookupService::class, $lookupService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/account/all',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

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

    public function testAccountRouteReturnsStandardErrorPayloadWhenResolverFails(): void
    {
        $client = static::createClient();

        $requestResolver = $this->createMock(AccountRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new \InvalidArgumentException('Invalid account payload.'));
        static::getContainer()->set(AccountRequestResolver::class, $requestResolver);

        $requestMapper = $this->createMock(AccountRequestMapper::class);
        $requestMapper->expects(self::never())->method('map');
        static::getContainer()->set(AccountRequestMapper::class, $requestMapper);

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService->expects(self::never())->method('handle');
        static::getContainer()->set(AccountLookupService::class, $lookupService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/account/all',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid payload or missing required data.',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}