<?php
namespace verbb\videopicker\helpers;

use Craft;

use yii\base\InvalidArgumentException;

/**
 * Guards Twig/generic embed URL fetching (SSRF + optional allowlist).
 */
class EmbedUrl
{
    // Static Methods
    // =========================================================================

    /** An empty allowed-domain list preserves the historical any-public-host default. */
    public static function assertAllowed(string $url, array $allowedDomains = []): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException(Craft::t('video-picker', 'Invalid embed URL.'));
        }

        $scheme = strtolower($parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL must use HTTP or HTTPS.'));
        }

        $host = self::normalizeHost((string)$parts['host']);

        if ($host === '') {
            throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL host is not allowed.'));
        }

        if ($allowedDomains) {
            $ok = false;

            foreach ($allowedDomains as $domain) {
                $domain = strtolower(trim((string)$domain));

                if ($domain === '') {
                    continue;
                }

                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    $ok = true;
                    break;
                }
            }

            if (!$ok) {
                throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL host is not in the allowed domains list.'));
            }
        }

        if (self::isBlockedHost($host)) {
            throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL host is not allowed.'));
        }
    }

    /** Resolve the approved host to public addresses that an HTTP client can pin. */
    public static function resolvePublicAddresses(string $url, array $allowedDomains = []): array
    {
        self::assertAllowed($url, $allowedDomains);

        $host = self::normalizeHost((string)parse_url($url, PHP_URL_HOST));

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (!$ip) {
                continue;
            }

            if (!self::isPublicIp($ip)) {
                throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL host is not allowed.'));
            }

            $addresses[] = $ip;
        }

        if (!$addresses) {
            throw new InvalidArgumentException(Craft::t('video-picker', 'Embed URL host could not be resolved.'));
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Strip brackets from IPv6 literals (`[::1]` → `::1`) so FILTER_VALIDATE_IP works.
     */
    public static function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return $host;
    }

    public static function isBlockedHost(string $host): bool
    {
        $host = self::normalizeHost($host);

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0.0.0.0') {
            return true;
        }

        // Literal IPs (IPv6 brackets already stripped).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !self::isPublicIp($host);
        }

        // Resolve DNS — block if any A/AAAA is private / reserved.
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (!$ip) {
                continue;
            }

            if (!self::isPublicIp($ip)) {
                return true;
            }
        }

        return false;
    }

    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton(trim($ip, '[]'));

        if ($packed === false) {
            return false;
        }

        $blocked = strlen($packed) === 4
            ? ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']
            : ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];

        // Only global unicast IPv6; exclude mapped, translation, and tunnel address families.
        if (strlen($packed) === 16 && (ord($packed[0]) & 0xe0) !== 0x20) {
            return false;
        }

        foreach ($blocked as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $network = inet_pton($network);
            $bytes = intdiv((int)$bits, 8);
            $remaining = (int)$bits % 8;

            if (substr($packed, 0, $bytes) === substr($network, 0, $bytes)
                && (!$remaining || (ord($packed[$bytes]) & (0xff << (8 - $remaining))) === (ord($network[$bytes]) & (0xff << (8 - $remaining))))) {
                return false;
            }
        }

        return true;
    }
}
