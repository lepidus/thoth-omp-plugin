<?php


use PHPUnit\Framework\TestCase;

final class ThothApiUrlGuardTest extends TestCase
{
    public function testAcceptsHttpsEndpointOnlyWhenEveryResolvedAddressIsPublic(): void
    {
        $resolvedHosts = [];
        $guard = new ThothApiUrlGuard(
            function (string $host) use (&$resolvedHosts): array {
                $resolvedHosts[] = $host;

                return ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'];
            }
        );

        $this->assertTrue($guard->isSafe('https://api.example.test/graphql'));
        $this->assertSame(['api.example.test'], $resolvedHosts);
    }

    /**
     * @dataProvider unsafeEndpointProvider
     */
    public function testRejectsUnsafeEndpointBeforeItCanReachTheClient(string $url, array $addresses): void
    {
        $guard = new ThothApiUrlGuard(fn (): array => $addresses);

        $this->assertFalse($guard->isSafe($url));
    }

    public static function unsafeEndpointProvider(): array
    {
        return [
            'plain HTTP' => ['http://api.example.test/graphql', ['93.184.216.34']],
            'credentials in URL' => ['https://user:password@api.example.test/graphql', ['93.184.216.34']],
            'loopback' => ['https://127.0.0.1/graphql', ['127.0.0.1']],
            'private address returned by DNS' => ['https://api.example.test/graphql', ['93.184.216.34', '10.0.0.2']],
            'host without addresses' => ['https://api.example.test/graphql', []],
        ];
    }
}
