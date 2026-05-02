<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Attribute\DesktopHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Annotation\Route;

final class ProtectedApiChannelPolicyInventoryTest extends TestCase
{
    public function testCriticalProtectedRoutesKeepTheirDeclaredChannelPolicies(): void
    {
        $inventory = $this->buildRouteInventory();

        foreach ($this->expectedPolicies() as $routeName => $expectedChannelAttribute) {
            self::assertArrayHasKey($routeName, $inventory, sprintf('Route "%s" must exist in the controller inventory.', $routeName));

            $method = $inventory[$routeName];
            $identifier = $method->class . '::' . $method->getName();

            self::assertNotEmpty($method->getAttributes(RequireHmac::class), $identifier . ' must keep RequireHmac.');
            self::assertNotEmpty($method->getAttributes(RequireJson::class), $identifier . ' must keep RequireJson.');

            if ($expectedChannelAttribute !== null) {
                self::assertNotEmpty($method->getAttributes($expectedChannelAttribute), $identifier . ' must keep the expected channel-specific HMAC attribute.');
            }

            foreach ([DesktopHmac::class, MobileHmac::class, ExtensionHmac::class] as $channelAttribute) {
                if ($expectedChannelAttribute !== null && $channelAttribute === $expectedChannelAttribute) {
                    continue;
                }

                self::assertEmpty($method->getAttributes($channelAttribute), $identifier . ' must not declare an unexpected channel-specific HMAC attribute.');
            }
        }
    }

    /** @return array<string, class-string|null> */
    private function expectedPolicies(): array
    {
        return [
            'account' => null,
            'api_nfc_users' => DesktopHmac::class,
            'api_nfc_decrypt' => DesktopHmac::class,
            'replace-device' => null,
            'replace-device-pin' => null,
            'create_secret' => null,
            'set-recovery-data' => null,
            'secure_device_qr_identity' => null,
            'user_registration_qr_identity' => null,
            'user_login_qr_identity' => null,
            'service_registration_business_Create' => null,
            'service_registration_corporate_data' => null,
            'service_registration_corporate_data_extend' => null,
            'shared_registration_qr_identity' => null,
            'shared_registration_new_to_encrypt' => MobileHmac::class,
            'shared_registration_new' => MobileHmac::class,
            'shared_registration_state' => ExtensionHmac::class,
            'one_touch_qr_identity' => null,
            'domain_read_credential_encrypted' => MobileHmac::class,
            'domain_read_credential' => MobileHmac::class,
            'domain_read_state' => ExtensionHmac::class,
            'domain_delete_credential' => MobileHmac::class,
            'domain_delete_state' => ExtensionHmac::class,
            'domain_delete_qr_identity' => null,
            'domain_read_qr_identity' => null,
            'vault_read_qr_identity' => null,
            'vault_read_credential_encrypted' => MobileHmac::class,
            'vault_read_credential' => MobileHmac::class,
            'vault_read_state' => ExtensionHmac::class,
            'vault_edit_qr_identity' => null,
            'vault_edit_credential' => MobileHmac::class,
            'vault_edit_state' => ExtensionHmac::class,
            'vault_delete_qr_identity' => null,
            'vault_delete_credential' => MobileHmac::class,
            'vault_delete_state' => ExtensionHmac::class,
            'one_touch_identifier' => MobileHmac::class,
            'one_touch_state' => ExtensionHmac::class,
        ];
    }

    /** @return array<string, \ReflectionMethod> */
    private function buildRouteInventory(): array
    {
        $inventory = [];

        foreach ($this->controllerClasses() as $className) {
            $reflectionClass = new \ReflectionClass($className);

            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflectionClass->getName()) {
                    continue;
                }

                $routeAttributes = $method->getAttributes(Route::class);
                if ($routeAttributes === []) {
                    continue;
                }

                $route = $routeAttributes[0]->newInstance();
                $name = $route->getName();

                if (is_string($name) && $name !== '') {
                    $inventory[$name] = $method;
                }
            }
        }

        ksort($inventory);

        return $inventory;
    }

    /** @return list<class-string> */
    private function controllerClasses(): array
    {
        $classes = [];
        $controllerDirectory = dirname(__DIR__, 2) . '/src/Controller';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDirectory));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents)) {
                continue;
            }

            if (preg_match('/^namespace\s+([^;]+);/m', $contents, $namespaceMatches) !== 1) {
                continue;
            }

            if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $classMatches) !== 1) {
                continue;
            }

            $classes[] = trim($namespaceMatches[1]) . '\\' . trim($classMatches[1]);
        }

        sort($classes);

        return $classes;
    }
}