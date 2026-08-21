<?php
namespace verbb\videopicker\helpers;

use Craft;

use yii\base\InvalidArgumentException;

/**
 * Guards Twig/generic embed URL fetching (SSRF + optional allowlist).
 */
class EmbedUrl
{
    // Public Methods
    // =========================================================================

    /**
     * @param string[] $allowedDomains Empty = any public host allowed (historical default).
     */
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

        $host = strtolower(rtrim($parts['host'], '.'));

        if ($host === '' || self::isBlockedHost($host)) {
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
    }

    public static function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0.0.0.0') {
            return true;
        }

        // Literal IPs (and IPv6 in brackets stripped by parse_url host).
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
