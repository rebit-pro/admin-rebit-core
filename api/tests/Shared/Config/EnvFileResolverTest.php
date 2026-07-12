<?php

declare(strict_types=1);

namespace App\Test\Shared\Config;

use App\Shared\Config\EnvFileResolver;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnvFileResolverTest extends TestCase
{
    private string $secretFile;

    protected function setUp(): void
    {
        $this->secretFile = tempnam(sys_get_temp_dir(), 'secret');
        file_put_contents($this->secretFile, "s3cr3t\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->secretFile);
        unset($_ENV['DB_PASSWORD'], $_SERVER['DB_PASSWORD']);
    }

    public function testFileValueOverridesPlainValue(): void
    {
        EnvFileResolver::resolve([
            'DB_PASSWORD' => 'from-dotenv',
            'DB_PASSWORD_FILE' => $this->secretFile,
        ]);

        self::assertSame('s3cr3t', $_ENV['DB_PASSWORD']);
        self::assertSame('s3cr3t', $_SERVER['DB_PASSWORD']);
    }

    public function testEmptyFileVariableIsIgnored(): void
    {
        $_ENV['DB_PASSWORD'] = 'kept';

        EnvFileResolver::resolve(['DB_PASSWORD_FILE' => '']);

        self::assertSame('kept', $_ENV['DB_PASSWORD']);
    }

    public function testUnreadableFileFailsLoudly(): void
    {
        $this->expectException(\RuntimeException::class);

        EnvFileResolver::resolve(['DB_PASSWORD_FILE' => '/nonexistent/secret']);
    }
}
