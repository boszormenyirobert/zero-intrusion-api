<?php

declare(strict_types=1);

namespace App\Service\Firebase;

final class FirebaseMessagePayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $deviceToken, string $title, string $body, mixed $qrData): array
    {
        return [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'ttl' => '7s',
                    'notification' => [
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                            'content-available' => 1,
                        ],
                    ],
                ],
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'action' => 'show_allow_close',
                    'message' => 'Allow or Decline login to the requested domain: ',
                    'qrData' => json_encode($qrData, JSON_THROW_ON_ERROR),
                ],
            ],
        ];
    }
}
