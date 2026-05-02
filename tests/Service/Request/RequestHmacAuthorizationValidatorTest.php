<?php

declare(strict_types=1);

namespace App\Tests\Service\Request;

use App\Service\Crypters\CrypterService;
use App\Service\Request\RequestHmacAuthorizationValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;

final class RequestHmacAuthorizationValidatorTest extends TestCase
{
    public function testValidateReturnsPayloadForValidAuthorizationHeader(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData(['domainProcessId' => 'process-123']);
        $encryptedData = $crypter->encryptData();
        $iv = base64_encode(random_bytes(16));
        $authHeader = sprintf(
            'HMAC client-key:%s',
            hash_hmac('sha256', sprintf('%s|%s', $encryptedData, $iv), 'secret-key')
        );

        $validator = new RequestHmacAuthorizationValidator($params, $logger);
        $request = Request::create('/api/account/all', 'POST', [], [], [], [
            'HTTP_X_AUTH' => $authHeader,
        ]);
        $payload = [
            'zeroIntrusionProyApi' => $encryptedData,
            'iv' => $iv,
        ];

        self::assertSame($payload, $validator->validateOrFail($request, $payload));
    }

    public function testValidateReturnsPayloadForTimestampedAuthorizationHeader(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData(['domainProcessId' => 'process-123']);
        $encryptedData = $crypter->encryptData();
        $iv = base64_encode(random_bytes(16));
        $authHeader = sprintf(
            'HMAC client-key:%s:%d',
            hash_hmac('sha256', sprintf('%s|%s', $encryptedData, $iv), 'secret-key'),
            time()
        );

        $validator = new RequestHmacAuthorizationValidator($params, $logger);
        $request = Request::create('/api/account/all', 'POST', [], [], [], [
            'HTTP_X_AUTH' => $authHeader,
        ]);
        $payload = [
            'zeroIntrusionProyApi' => $encryptedData,
            'iv' => $iv,
        ];

        self::assertSame($payload, $validator->validateOrFail($request, $payload));
    }

    public function testValidateOrFailThrowsForInvalidAuthorizationHeaderFormat(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $validator = new RequestHmacAuthorizationValidator($this->createParameterBag(), $logger);
        $request = Request::create('/api/account/all', 'POST', [], [], [], ['HTTP_X_AUTH' => 'invalid']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Authorization header format');

        $validator->validateOrFail($request, ['zeroIntrusionProyApi' => 'encrypted', 'iv' => 'iv-value']);
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
            ['SERVICE_API_KEY', 'client-key'],
            ['SERVICE_API_SECRET', 'secret-key'],
        ]);

        return $params;
    }
}
