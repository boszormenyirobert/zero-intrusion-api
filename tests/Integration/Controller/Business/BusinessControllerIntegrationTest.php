<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Business;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Business\BusinessCreateRequestDTO;
use App\DTO\Business\BusinessCreateResponseDTO;
use App\Kernel;
use App\Service\Business\BusinessCreateRequestMapper;
use App\Service\Business\BusinessCreateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BusinessControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testBusinessCreateReturnsGeneratedResponse(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn(['business_create' => '{"publicId":"public-1"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(BusinessCreateRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(BusinessCreateRequestDTO::fromArray([
                'businessModel' => 'businessBasic',
                'publicId' => 'public-1',
                'scope' => 'external',
            ]));
        static::getContainer()->set(BusinessCreateRequestMapper::class, $requestMapper);

        $businessCreateService = $this->createMock(BusinessCreateService::class);
        $businessCreateService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new BusinessCreateResponseDTO('encrypted-body', ['X-Test' => 'header']));
        static::getContainer()->set(BusinessCreateService::class, $businessCreateService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/business/create',
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
        self::assertSame('encrypted-body', (string) $client->getResponse()->getContent());
        self::assertSame('header', $client->getResponse()->headers->get('X-Test'));
    }

    public function testBusinessCreateReturnsStandardErrorPayloadOnValidationFailure(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willThrowException(new \InvalidArgumentException('Invalid business create payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/business/create',
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