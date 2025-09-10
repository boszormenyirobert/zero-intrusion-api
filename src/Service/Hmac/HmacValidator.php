<?php

namespace App\Service\Hmac;

use App\Exception\InvalidHmacException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Repository\AuthBridgeRepository;
use Psr\Log\LoggerInterface;
use App\Service\Crypters\CrypterDatabaseLoginService;

class HmacValidator
{
    public function __construct(
        private ParameterBagInterface $params,
        private AuthBridgeRepository $authBridgeRepository,
        private LoggerInterface $logger,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService
    ) {}

    public function validate(string $payload, ?string $authHeader, string $iv, array $encryptedData)
    {
        if (!$authHeader || !preg_match("/^HMAC (\S+):(\S+):(\d+)$/", $authHeader, $matches)) {
            throw new InvalidHmacException('Invalid Authentication header format');
        }

        $apiKey = trim($matches[1]);
        $recvSignature = trim($matches[2]);
        $timestamp = (int) $matches[3];

        $expectedKey = $this->params->get('SERVICE_API_KEY');
        $expectedSecret = $this->params->get('SERVICE_API_SECRET');

        if ($apiKey !== $expectedKey) {
            throw new InvalidHmacException('Unknown API key');
        }

        $currentTime = time();
        $elapsed = $currentTime - $timestamp;

        // 2 Min
        if ($elapsed > 120) {
            throw new InvalidHmacException('HMAC timestamp expired');
        }

        $message = $encryptedData['zeroIntrusionProyApi'] . '|' . $iv;
        $expectedSignature = hash_hmac('sha256', $message, $expectedSecret);

        if (!hash_equals($expectedSignature, $recvSignature)) {
            throw new InvalidHmacException('Invalid HMAC signature');
        }
        return true;
    }

    public function extensionValidate(?string $authHeader, $payload, $type = "domainProcessId")
    {
        if (!$authHeader || !preg_match("/^HMAC (\S+)$/", $authHeader, $matches)) {
            $this->logger->critical('Invalid Authentication header format');
            return false;
        }

        $payload = json_decode($payload, true);

        $recvSignature = trim($matches[1]);
        $recIv = $payload['iv'] ?? null;
        $domainProcessId = $payload[$type] ?? null;

        if (!$recvSignature) {
            $this->logger->critical('Missing HMAC signature');
            return false;
        }

        if (!$recIv) {
            $this->logger->critical('Missing IV');
            return false;
        }

        $expectedBridgeFromDb = $this->authBridgeRepository->findOneBy([$type => $domainProcessId]);
        if (!$expectedBridgeFromDb) {
            $this->logger->critical('No record found for domainProcessId: ' . $domainProcessId);
            return false;
        }

        $ivFromDb = $expectedBridgeFromDb->getIv();

        if ($recIv !== $ivFromDb) {
            $this->logger->critical('IV mismatch');
            return false;
        }


        $bridgeFromDb = $this->crypterDatabaseLoginService->decryptFromDatabaseToHmac($expectedBridgeFromDb);

        $secretFromDb = $bridgeFromDb->getSecret();


        $expectedSignature = hash_hmac('sha256', $domainProcessId, $secretFromDb);

        if (!hash_equals($expectedSignature, $recvSignature)) {
            $this->logger->critical('HMAC ERROR');
            return false;
        }

        return true;
    }
}
