<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\DTO\Response\ResponseDataInterface;
use App\DTO\QR\OneTouchDTO;
use App\Helper\ResponseHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ResponseHelperTest extends TestCase
{
    public function testCreateSuccessResponseAcceptsTopLevelExplicitResponseData(): void
    {
        $helper = new ResponseHelper($this->createMock(LoggerInterface::class));

        $response = $helper->createSuccessResponse(new class implements ResponseDataInterface {
            public function toResponseArray(): array
            {
                return [
                    'process' => true,
                    'validation' => 'ok',
                    'process_check' => false,
                    'publicId' => 'public-1',
                ];
            }
        });

        self::assertSame(
            [
                'process' => true,
                'validation' => 'ok',
                'process_check' => false,
                'success' => true,
                'publicId' => 'public-1',
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateSuccessResponseNormalizesExplicitResponseDataObjects(): void
    {
        $helper = new ResponseHelper($this->createMock(LoggerInterface::class));

        $response = $helper->createSuccessResponse([
            'payload' => new class implements ResponseDataInterface {
                public function toResponseArray(): array
                {
                    return ['explicit' => true];
                }
            },
        ]);

        self::assertSame(
            [
                'process' => false,
                'validation' => false,
                'process_check' => false,
                'success' => true,
                'payload' => ['explicit' => true],
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateSuccessResponseNormalizesSupportedObjectPayloads(): void
    {
        $helper = new ResponseHelper($this->createMock(LoggerInterface::class));

        $domainObject = new class {
            public function toDomainStateArray(): array
            {
                return ['domain' => true];
            }
        };

        $arrayObject = new class {
            public function toArray(): array
            {
                return ['array' => true];
            }
        };

        $jsonObject = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['json' => true];
            }
        };

        $oneTouchDto = new OneTouchDTO('process-1', 'auth-1', 'type', 'extension', 'public-1', 'target-1');
        $oneTouchDto->setValidCommunication(['mobile']);
        $oneTouchDto->setCreatedAt('2026-04-27');
        $oneTouchDto->setXExtensionAuthTwo('auth-2');
        $oneTouchDto->setSecret('secret');
        $oneTouchDto->setIv('iv');
        $oneTouchDto->setQrCode('qr-code');

        $response = $helper->createSuccessResponse([
            'process' => true,
            'domainObject' => $domainObject,
            'arrayObject' => $arrayObject,
            'jsonObject' => $jsonObject,
            'oneTouch' => $oneTouchDto,
            'plain' => 'value',
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [
                'process' => true,
                'validation' => false,
                'process_check' => false,
                'success' => true,
                'domainObject' => ['domain' => true],
                'arrayObject' => ['array' => true],
                'jsonObject' => ['json' => true],
                'oneTouch' => [
                    'validCommunication' => ['mobile'],
                    'createdAt' => '2026-04-27',
                    'xExtensionAuthOne' => 'auth-1',
                    'xExtensionAuthTwo' => 'auth-2',
                    'secret' => 'secret',
                    'iv' => 'iv',
                    'oneTouchProcessId' => 'process-1',
                    'qrCode' => 'qr-code',
                ],
                'plain' => 'value',
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateSuccessResponseAlwaysMarksPayloadSuccessful(): void
    {
        $helper = new ResponseHelper($this->createMock(LoggerInterface::class));

        $response = $helper->createSuccessResponse([
            'success' => false,
            'payload' => ['id' => 'public-1'],
        ]);

        self::assertSame(
            [
                'process' => false,
                'validation' => false,
                'process_check' => false,
                'success' => true,
                'payload' => ['id' => 'public-1'],
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateErrorResponseLogsCriticalAndUsesProvidedStatusCode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('failure');

        $helper = new ResponseHelper($logger);
        $response = $helper->createErrorResponse('failure', Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'error' => 'failure'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateProcessResponseAlwaysUsesHttp200(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('process-error');

        $helper = new ResponseHelper($logger);
        $response = $helper->createProcessResponse('process-error');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'error' => 'process-error'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testHandleExceptionLogsOnceAndReturnsStandardErrorResponse(): void
    {
        $exception = new \RuntimeException('boom');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('boom');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('An error occurred', ['error' => 'boom', 'context' => 'value']);

        $helper = new ResponseHelper($logger);
        $response = $helper->handleException($exception, ['context' => 'value']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'error' => 'Invalid payload or missing required data.'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
