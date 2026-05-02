<?php

declare(strict_types=1);

namespace App\Tests\Service\Shared;

use App\Service\Crypters\CrypterService;
use App\Service\Payload\EncryptedPayloadDecoder;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Request\JsonRequestEnvelopeValidator;
use App\Service\Request\RequestHmacAuthorizationValidator;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;

final class RequestServiceTest extends TestCase
{
    public function testValidateRequestAcceptsAuthorizationHeaderGeneratedForPayload(): void
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
        $request = Request::create(
            '/api/account/all',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_AUTH' => $authHeader],
            json_encode([
                'zeroIntrusionProyApi' => $encryptedData,
                'iv' => $iv,
            ], JSON_THROW_ON_ERROR)
        );

        $service = $this->createService($params, $crypter, $logger);

        self::assertSame(
            [
                'zeroIntrusionProyApi' => $encryptedData,
                'iv' => $iv,
            ],
            $service->validateRequestOrFail($request)
        );
    }

    public function testValidateRequestReturnsErrorForInvalidJson(): void
    {
        $service = $this->createService();
        $request = Request::create('/api/account/all', 'POST', [], [], [], ['HTTP_X_AUTH' => 'HMAC client-key:signature'], '{invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        $service->validateRequestOrFail($request);
    }

    public function testValidateRequestPreservesLegacyErrorArrayForInvalidJson(): void
    {
        $service = $this->createService();
        $request = Request::create('/api/account/all', 'POST', [], [], [], ['HTTP_X_AUTH' => 'HMAC client-key:signature'], '{invalid');

        self::assertSame(['error' => 'Invalid JSON payload'], $service->validateRequest($request));
    }

    public function testValidPayloadDecryptsZeroIntrusionProxyPayload(): void
    {
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData(['business_create' => ['publicId' => 'public-1']]);
        $encryptedData = $crypter->encryptData();

        $service = $this->createService($params, new CrypterService($params));

        self::assertSame(
            ['business_create' => ['publicId' => 'public-1']],
            $service->validPayloadOrFail(['zeroIntrusionProyApi' => $encryptedData])
        );
    }

    public function testValidPayloadThrowsForInvalidDecryptedJson(): void
    {
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData('scalar-payload');
        $encryptedData = $crypter->encryptData();

        $service = $this->createService($params, $crypter);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid encrypted payload.');

        $service->validPayloadOrFail(['zeroIntrusionProyApi' => $encryptedData]);
    }

    public function testValidPayloadPreservesLegacyNullForInvalidDecryptedJson(): void
    {
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData('scalar-payload');
        $encryptedData = $crypter->encryptData();

        $service = $this->createService($params, $crypter);

        self::assertNull($service->validPayload(['zeroIntrusionProyApi' => $encryptedData]));
    }

    private function createService(
        ?ContainerBagInterface $params = null,
        ?CrypterService $crypterService = null,
        ?LoggerInterface $logger = null,
    ): RequestService {
        $params ??= $this->createParameterBag();
        $logger ??= $this->createMock(LoggerInterface::class);
        $crypterService ??= new CrypterService($params);

        return new RequestService(
            new JsonRequestEnvelopeValidator(),
            new RequestHmacAuthorizationValidator($params, $logger),
            new EncryptedPayloadDecoder($crypterService, new JsonPayloadDecoder(), $logger),
        );
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
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
