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

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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
            return !filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        // Resolve DNS — block if any A/AAAA is private / reserved.
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (!$ip) {
                continue;
            }

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }
}
