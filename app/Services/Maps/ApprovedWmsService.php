<?php

namespace App\Services\Maps;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ApprovedWmsService
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_WFS_VERSIONS = ['1.0.0', '1.1.0', '2.0.0'];

    private const XML_CONTENT_TYPES = [
        'application/xml',
        'text/xml',
        'application/vnd.ogc.wms_xml',
    ];

    public function serverOptions(): array
    {
        $options = [];

        foreach ((array) config('wms_proxy.servers', []) as $id => $server) {
            if (!is_array($server) || !$this->hasRequiredServerSettings($server)) {
                continue;
            }

            $options[$id] = $server['label'] ?? $id;
        }

        return $options;
    }

    /**
     * @throws ConnectionException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function fetchCapabilities(string $serverId, string $version): array
    {
        $server = $this->approvedServer($serverId);
        $allowedVersions = (array) config('wms_proxy.allowed_versions', []);

        if (!in_array($version, $allowedVersions, true)) {
            throw new InvalidArgumentException('Unsupported WMS version.');
        }

        $wmsUrl = $this->validateConfiguredEndpoint($server['wms_url'], 'WMS');
        $wfsUrl = $this->validateConfiguredEndpoint($server['wfs_url'], 'WFS');
        $wfsVersion = (string) ($server['wfs_version'] ?? '1.0.0');

        if (!in_array($wfsVersion, self::ALLOWED_WFS_VERSIONS, true)) {
            throw new RuntimeException('The approved WFS version is invalid.');
        }

        $response = Http::withOptions([
            'allow_redirects' => false,
            'connect_timeout' => (float) config('wms_proxy.connect_timeout_seconds', 3),
        ])
            ->accept('application/xml, text/xml, application/vnd.ogc.wms_xml')
            ->timeout((int) config('wms_proxy.timeout_seconds', 10))
            ->get($wmsUrl, [
                'SERVICE' => 'WMS',
                'REQUEST' => 'GetCapabilities',
                'VERSION' => $version,
            ]);

        if ($response->redirect()) {
            throw new RuntimeException('The approved WMS server returned a redirect.');
        }

        if (!$response->successful()) {
            throw new RuntimeException('The approved WMS server could not be reached.');
        }

        $body = $response->body();
        $maxBytes = (int) config('wms_proxy.max_response_bytes', 2 * 1024 * 1024);

        if ($body === '' || strlen($body) > $maxBytes) {
            throw new RuntimeException('The WMS response is empty or too large.');
        }

        $this->validateXmlContentType($response->header('Content-Type'));
        $this->validateCapabilitiesXml($body);

        return [
            'capabilities' => $body,
            'version' => $version,
            'wms_url' => $wmsUrl,
            'wfs_url' => $wfsUrl,
            'wfs_version' => $wfsVersion,
            'workspace' => (string) ($server['workspace'] ?? ''),
        ];
    }

    private function approvedServer(string $serverId): array
    {
        if (!preg_match('/\A[a-z0-9_-]+\z/', $serverId)) {
            throw new InvalidArgumentException('Invalid WMS server selection.');
        }

        $server = config('wms_proxy.servers.' . $serverId);

        if (!is_array($server) || !$this->hasRequiredServerSettings($server)) {
            throw new InvalidArgumentException('The selected WMS server is not approved.');
        }

        return $server;
    }

    private function hasRequiredServerSettings(array $server): bool
    {
        return !empty($server['wms_url']) && !empty($server['wfs_url']);
    }

    private function validateConfiguredEndpoint(string $url, string $service): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException("The approved {$service} URL is invalid.");
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new RuntimeException("The approved {$service} URL has an unsupported scheme.");
        }

        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException("The approved {$service} URL is not safe.");
        }

        if (!empty($parts['query']) || !empty($parts['fragment'])) {
            throw new RuntimeException("The approved {$service} URL must not contain a query or fragment.");
        }

        return $url;
    }

    private function validateXmlContentType(?string $contentType): void
    {
        $normalized = strtolower(trim(explode(';', (string) $contentType, 2)[0]));

        if (!in_array($normalized, self::XML_CONTENT_TYPES, true)) {
            throw new RuntimeException('The WMS server did not return XML.');
        }
    }

    private function validateCapabilitiesXml(string $body): void
    {
        if (stripos($body, '<!DOCTYPE') !== false) {
            throw new RuntimeException('The WMS response contains a disallowed document type.');
        }

        $previousSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if ($xml === false || !in_array($xml->getName(), ['WMS_Capabilities', 'WMT_MS_Capabilities'], true)) {
            throw new RuntimeException('The response is not a WMS capabilities document.');
        }
    }
}
