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

final class RoutePolicyInventoryTest extends TestCase
{
    public function testEveryApiRouteDeclaresCoreTransportPolicies(): void
    {
        $inventory = [];
        $violations = [];

        foreach ($this->controllerClasses() as $className) {
            $reflectionClass = new \ReflectionClass($className);

            if ($reflectionClass->isAbstract()) {
                continue;
            }

            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflectionClass->getName()) {
                    continue;
                }

                $routeAttributes = $method->getAttributes(Route::class);

                if ($routeAttributes === []) {
                    continue;
                }

                $route = $routeAttributes[0]->newInstance();
                $path = $route->getPath();

                if (!is_string($path) || !str_starts_with($this->resolveRoutePath($reflectionClass, $path), '/api')) {
                    continue;
                }

                $inventory[] = $method->class . '::' . $method->getName();

                $identifier = sprintf('%s::%s [%s]', $method->class, $method->getName(), $this->resolveRoutePath($reflectionClass, $path));

                if ($method->getAttributes(RequireHmac::class) === []) {
                    $violations[] = $identifier . ' must declare RequireHmac';
                }

                if ($method->getAttributes(RequireJson::class) === []) {
                    $violations[] = $identifier . ' must declare RequireJson';
                }

                $channelMarkers = 0;
                foreach ([DesktopHmac::class, MobileHmac::class, ExtensionHmac::class] as $attributeClass) {
                    if ($method->getAttributes($attributeClass) !== []) {
                        ++$channelMarkers;
                    }
                }

                if ($channelMarkers > 1) {
                    $violations[] = $identifier . ' must not declare multiple channel-specific HMAC markers';
                }
            }
        }

        sort($inventory);
        sort($violations);

        self::assertNotEmpty($inventory, 'API route inventory must not be empty.');
        self::assertSame([], $violations, "API route policy inventory violations:\n- " . implode("\n- ", $violations));
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

            $namespace = $this->extractNamespace($contents);
            $className = $this->extractClassName($contents);

            if ($namespace === null || $className === null) {
                continue;
            }

            $classes[] = $namespace . '\\' . $className;
        }

        sort($classes);

        return $classes;
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function resolveRoutePath(\ReflectionClass $reflectionClass, string $methodPath): string
    {
        $classRouteAttributes = $reflectionClass->getAttributes(Route::class);

        if ($classRouteAttributes === []) {
            return $methodPath;
        }

        $classRoute = $classRouteAttributes[0]->newInstance();
        $classPath = $classRoute->getPath();

        if (!is_string($classPath)) {
            return $methodPath;
        }

        return rtrim($classPath, '/') . '/' . ltrim($methodPath, '/');
    }
}