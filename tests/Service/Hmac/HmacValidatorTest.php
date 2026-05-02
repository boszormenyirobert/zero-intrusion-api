<?php

declare(strict_types=1);

namespace App\Tests\Service\Hmac;

use App\Entity\AuthBridge;
use App\Exception\InvalidHmacException;
use App\Repository\AuthBridgeRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Hmac\HmacValidator;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class HmacValidatorTest extends TestCase
{
    public function testValidateAcceptsMatchingHmacHeader(): void
    {
        $validator = $this->createValidator();
        $encryptedData = ['zeroIntrusionProyApi' => 'encrypted-payload'];
        $iv = 'iv-base64';
        $timestamp = time();
        $signature = hash_hmac('sha256', $encryptedData['zeroIntrusionProyApi'] . '|' . $iv, 'secret-key');
        $authHeader = sprintf('HMAC %s:%s:%d', 'client-key', $signature, $timestamp);

        self::assertTrue($validator->validate('payload', $authHeader, $iv, $encryptedData));
    }

    public function testValidateRejectsExpiredTimestamp(): void
    {
        $validator = $this->createValidator();
        $encryptedData = ['zeroIntrusionProyApi' => 'encrypted-payload'];
        $iv = 'iv-base64';
        $timestamp = time() - 121;
        $signature = hash_hmac('sha256', $encryptedData['zeroIntrusionProyApi'] . '|' . $iv, 'secret-key');
        $authHeader = sprintf('HMAC %s:%s:%d', 'client-key', $signature, $timestamp);

        $this->expectException(InvalidHmacException::class);
        $this->expectExceptionMessage('HMAC timestamp expired');

        $validator->validate('payload', $authHeader, $iv, $encryptedData);
    }

    public function testExtensionValidateAcceptsMatchingProcessIdSignature(): void
    {
        $authBridge = (new AuthBridge())
            ->setIv('stored-iv')
            ->setSecret('encrypted-secret')
            ->setDomainProcessId('process-123');

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['domainProcessId' => 'process-123'])
            ->willReturn($authBridge);

        $decryptedBridge = (new AuthBridge())->setSecret('secret-from-db');
        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabaseToHmac')
            ->with($authBridge)
            ->willReturn($decryptedBridge);

        $validator = $this->createValidator(repository: $repository, crypter: $crypter);
        $signature = hash_hmac('sha256', 'process-123', 'secret-from-db');
        $payload = json_encode([
            'iv' => 'stored-iv',
            'domainProcessId' => 'process-123',
        ], JSON_THROW_ON_ERROR);

        self::assertTrue($validator->extensionValidate('HMAC ' . $signature, $payload));
    }

    public function testExtensionValidateReturnsFalseForInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('Invalid extension payload JSON');

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $validator = $this->createValidator(logger: $logger, repository: $repository);

        self::assertFalse($validator->extensionValidate('HMAC signature', '{invalid-json'));
    }

    private function createValidator(
        ?ParameterBagInterface $params = null,
        ?AuthBridgeRepository $repository = null,
        ?LoggerInterface $logger = null,
        ?CrypterDatabaseLoginService $crypter = null,
    ): HmacValidator {
        return new HmacValidator(
            $params ?? $this->createParameterBag(),
            $repository ?? $this->createMock(AuthBridgeRepository::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $crypter ?? $this->createMock(CrypterDatabaseLoginService::class),
            new JsonPayloadDecoder(),
        );
    }

    private function createParameterBag(): ParameterBagInterface&MockObject
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['SERVICE_API_KEY', 'client-key'],
                ['SERVICE_API_SECRET', 'secret-key'],
            ]);

        return $params;
    }
}
