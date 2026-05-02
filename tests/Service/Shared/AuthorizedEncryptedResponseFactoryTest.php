<?php

declare(strict_types=1);

namespace App\Tests\Service\Shared;

use App\Helper\AuthorizationHelper;
use App\Helper\AuthorizationHelperFactory;
use App\Service\Crypters\CrypterService;
use App\Service\Shared\AuthorizedEncryptedResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class AuthorizedEncryptedResponseFactoryTest extends TestCase
{
    public function testCreateBuildsEncryptedAuthorizedResponse(): void
    {
        $authorizationHelper = new AuthorizationHelper(
            'client-key',
            'secret-key',
            $this->createMock(LoggerInterface::class),
        );

        $authorizationHelperFactory = $this->createMock(AuthorizationHelperFactory::class);
        $authorizationHelperFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($authorizationHelper);

        $factory = new AuthorizedEncryptedResponseFactory(
            new CrypterService($this->createParameterBag()),
            $authorizationHelperFactory,
        );

        $result = $factory->create([
            'domainProcessId' => 'process-1',
            'qrCode' => 'qr-code',
        ]);

        self::assertSame('application/json', $result['headers']['Content-Type']);
        self::assertArrayHasKey('X-Auth', $result['headers']);

        $body = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('corporateIdentity', $body);
        self::assertArrayHasKey('iv', $body);
    }

    private function createParameterBag(): ContainerBagInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
                ['SERVICE_API_KEY', 'client-key'],
                ['SERVICE_API_SECRET', 'secret-key'],
            ]);

        return $params;
    }
}