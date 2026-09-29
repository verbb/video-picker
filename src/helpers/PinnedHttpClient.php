<?php
namespace verbb\videopicker\helpers;

use Closure;
use RuntimeException;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/** HTTP client that pins every approved URL and redirect to a validated public IP. */
class PinnedHttpClient implements ClientInterface
{
    // Properties
    // =========================================================================

    private array $_allowedDomains;
    private array $_settings;
    private ?Closure $_resolver;
    private ?Closure $_transport;


    // Public Methods
    // =========================================================================

    public function __construct(
        array $allowedDomains = [],
        array $settings = [],
        ?Closure $resolver = null,
        ?Closure $transport = null,
    )
    {
        $this->_allowedDomains = $allowedDomains;
        $this->_settings = $settings;
        $this->_resolver = $resolver;
        $this->_transport = $transport;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $maxRedirects = max(0, min(5, (int)($this->_settings['max_redirs'] ?? 3)));

        for ($redirects = 0; ; $redirects++) {
            $response = $this->_sendPinned($request);
            $status = $response->getStatusCode();

            if ($status < 300 || $status >= 400 || !$response->hasHeader('Location')) {
                return $response;
            }

            if ($redirects >= $maxRedirects) {
                throw new RuntimeException('Embed URL exceeded the redirect limit.');
            }

            $location = new Uri($response->getHeaderLine('Location'));
            $uri = UriResolver::resolve($request->getUri(), $location);
            $previousUri = $request->getUri();
            $request = $request->withUri($uri);

            // Redirects may cross between allowed provider/CDN hosts. Never carry
            // credentials from one origin to another, even when both are allowlisted.
            if (
                strtolower($previousUri->getScheme()) !== strtolower($uri->getScheme())
                || strtolower($previousUri->getHost()) !== strtolower($uri->getHost())
                || $this->_effectivePort($previousUri) !== $this->_effectivePort($uri)
            ) {
                foreach (['Authorization', 'Cookie', 'Proxy-Authorization'] as $header) {
                    $request = $request->withoutHeader($header);
                }
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _effectivePort(UriInterface $uri): int
    {
        return $uri->getPort() ?: (strtolower($uri->getScheme()) === 'https' ? 443 : 80);
    }

    private function _sendPinned(RequestInterface $request): ResponseInterface
    {
        $url = (string)$request->getUri();
        $addresses = $this->_resolver
            ? ($this->_resolver)($url, $this->_allowedDomains)
            : EmbedUrl::resolvePublicAddresses($url, $this->_allowedDomains);
        $host = EmbedUrl::normalizeHost($request->getUri()->getHost());
        $port = $request->getUri()->getPort() ?: ($request->getUri()->getScheme() === 'https' ? 443 : 80);
        $pinnedAddress = $addresses[0];
        $curlAddress = str_contains($pinnedAddress, ':') ? '[' . $pinnedAddress . ']' : $pinnedAddress;
        $protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $maxBytes = max(1, (int)($this->_settings['max_bytes'] ?? 5_000_000));
        $sink = Utils::streamFor('');

        $options = [
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => (float)($this->_settings['connect_timeout'] ?? 5),
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::PROXY => '',
            // Guzzle's cURL handler omits handler stats for streamed responses,
            // which makes the peer-IP verification below fail closed even when
            // CURLOPT_RESOLVE pinned the approved address.
            RequestOptions::STREAM => false,
            // Transfer progress can count compressed bytes. Bound the decoded sink too.
            RequestOptions::SINK => FnStream::decorate($sink, [
                'write' => static function(string $data) use ($sink, $maxBytes): int {
                    if ($sink->tell() + strlen($data) > $maxBytes) {
                        throw new RuntimeException('Embed response exceeded the download limit.');
                    }

                    return $sink->write($data);
                },
            ]),
            RequestOptions::TIMEOUT => (float)($this->_settings['timeout'] ?? 8),
            RequestOptions::VERIFY => $this->_settings['ssl_verify_peer'] ?? true,
            RequestOptions::CURL => [
                CURLOPT_MAXFILESIZE => $maxBytes,
                CURLOPT_PROTOCOLS => $protocols,
                CURLOPT_REDIR_PROTOCOLS => $protocols,
                CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $curlAddress)],
            ],
            RequestOptions::PROGRESS => static function(float $downloadTotal, float $downloadedBytes) use ($maxBytes): void {
                if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
                    throw new RuntimeException('Embed response exceeded the download limit.');
                }
            },
            RequestOptions::ON_STATS => static function(TransferStats $stats) use ($addresses): void {
                $primaryIp = $stats->getHandlerStat('primary_ip');
                $primaryPacked = is_string($primaryIp) ? @inet_pton($primaryIp) : false;
                $approved = array_filter(array_map(static fn(string $ip) => @inet_pton($ip), $addresses));

                if ($primaryPacked === false || !in_array($primaryPacked, $approved, true)) {
                    throw new RuntimeException('Embed connection did not use the validated address.');
                }
            },
        ];

        if ($this->_transport) {
            return ($this->_transport)($request, $options);
        }

        return (new Client())->send($request, $options);
    }
}
