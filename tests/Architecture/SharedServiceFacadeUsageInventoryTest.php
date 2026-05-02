<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SharedServiceFacadeUsageInventoryTest extends TestCase
{
    public function testSharedServiceFacadeHasBeenRemovedFromTheSourceTree(): void
    {
        $srcDirectory = dirname(__DIR__, 2) . '/src';
        $sharedServiceFile = $srcDirectory . '/Service/CredentialHub/SharedService.php';
        $violations = [];

        self::assertFileDoesNotExist($sharedServiceFile, 'SharedService facade file should be deleted once all collaborators are injected directly.');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDirectory));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents)) {
                continue;
            }

            if (
                str_contains($contents, 'use App\\Service\\CredentialHub\\SharedService;')
                || preg_match('/\bSharedService\s*\$/', $contents) === 1
                || str_contains($contents, 'SharedService::class')
                || str_contains($contents, 'new SharedService(')
            ) {
                $violations[] = str_replace(dirname(__DIR__, 2) . '/', '', $path);
            }
        }

        sort($violations);

        self::assertSame([], $violations, "SharedService facade references must be removed from the source tree:\n- " . implode("\n- ", $violations));
    }
}