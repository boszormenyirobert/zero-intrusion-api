<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Corporate;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Corporate\CorporateFollowUpRequestDTO;
use App\DTO\Corporate\CorporateFollowUpResultDTO;
use App\DTO\Corporate\CorporateIdentityInitializeRequestDTO;
use App\DTO\Corporate\CorporateInitializeResponseDTO;
use App\Kernel;
use App\Service\Corporate\CorporateFollowUpRequestMapper;
use App\Service\Corporate\CorporateFollowUpService;
use App\Service\Corporate\CorporateIdentityInitializeRequestMapper;
use App\Service\Corporate\CorporateIdentityInitializeService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CorporateRegistrationControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testServiceIdentityReturnsInitializedCorporateResponse(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn(['getIdentity' => '{"publicId":"public-1"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(CorporateIdentityInitializeRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(CorporateIdentityInitializeRequestDTO::fromArray([
                'publicId' => 'public-1',
                'scope' => 'internal',
                'businessModel' => 'businessBasic',
            ]));
        static::getContainer()->set(CorporateIdentityInitializeRequestMapper::class, $requestMapper);

        $initializeService = $this->createMock(CorporateIdentityInitializeService::class);
        $initializeService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new CorporateInitializeResponseDTO('encrypted-body', ['X-Test' => 'header']));
        static::getContainer()->set(CorporateIdentityInitializeService::class, $initializeService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/identity/create/initialize',
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

    public function testServiceIdentityReturnsStandardErrorPayloadWhenValidationFails(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willThrowException(new \InvalidArgumentException('Invalid corporate initialize payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/identity/create/initialize',
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

    public function testServiceRegistrationReturnsSuccessMarkerForFollowUp(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn(['updateIdentity' => '{"corporateId":"corp-1"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(CorporateFollowUpRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new CorporateFollowUpRequestDTO([
                'updateIdentity' => [
                    'corporateId' => 'corp-1',
                ],
            ]));
        static::getContainer()->set(CorporateFollowUpRequestMapper::class, $requestMapper);

        $followUpService = $this->createMock(CorporateFollowUpService::class);
        $followUpService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(CorporateFollowUpResultDTO::success());
        static::getContainer()->set(CorporateFollowUpService::class, $followUpService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/identity/create/follow-up',
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
        self::assertSame('1', (string) $client->getResponse()->getContent());
    }

    public function testServiceRegistrationReturnsErrorPayloadForFollowUpDomainErrors(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn(['updateIdentity' => '{"corporateId":"corp-1"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(CorporateFollowUpRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new CorporateFollowUpRequestDTO([
                'updateIdentity' => [
                    'corporateId' => 'corp-1',
                ],
            ]));
        static::getContainer()->set(CorporateFollowUpRequestMapper::class, $requestMapper);

        $followUpService = $this->createMock(CorporateFollowUpService::class);
        $followUpService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(CorporateFollowUpResultDTO::error([
                'error' => true,
                'message' => 'CorporateId missing in the follow-up data',
            ]));
        static::getContainer()->set(CorporateFollowUpService::class, $followUpService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/registration/corporate/identity/create/follow-up',
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
            'error' => true,
            'message' => 'CorporateId missing in the follow-up data',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}