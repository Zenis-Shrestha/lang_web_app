<?php

namespace Tests\Unit;

use App\Services\Maps\ApprovedWmsService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ApprovedWmsServiceTest extends TestCase
{
    private const CAPABILITIES_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<WMS_Capabilities version="1.3.0">
    <Service><Name>WMS</Name></Service>
    <Capability><Layer><Name>approved_layer</Name></Layer></Capability>
</WMS_Capabilities>
XML;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'wms_proxy.allowed_versions' => ['1.1.1', '1.3.0'],
            'wms_proxy.connect_timeout_seconds' => 3,
            'wms_proxy.timeout_seconds' => 10,
            'wms_proxy.max_response_bytes' => 1024 * 1024,
            'wms_proxy.servers' => [
                'approved_server' => [
                    'label' => 'Approved Server',
                    'wms_url' => 'https://maps.example.test/geoserver/workspace/wms',
                    'wfs_url' => 'https://maps.example.test/geoserver/workspace/wfs',
                    'wfs_version' => '1.0.0',
                    'workspace' => 'workspace',
                ],
            ],
        ]);
    }

    public function test_it_builds_get_capabilities_request_from_approved_configuration(): void
    {
        Http::fake([
            'https://maps.example.test/*' => Http::response(
                self::CAPABILITIES_XML,
                200,
                ['Content-Type' => 'text/xml; charset=UTF-8']
            ),
        ]);

        $result = app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );

        $this->assertSame(self::CAPABILITIES_XML, $result['capabilities']);
        $this->assertSame('workspace', $result['workspace']);
        $this->assertSame('1.0.0', $result['wfs_version']);
        $this->assertSame(
            'https://maps.example.test/geoserver/workspace/wms',
            $result['wms_url']
        );
        $this->assertSame(
            'https://maps.example.test/geoserver/workspace/wfs',
            $result['wfs_url']
        );

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_PATH) === '/geoserver/workspace/wms'
                && ($query['SERVICE'] ?? null) === 'WMS'
                && ($query['REQUEST'] ?? null) === 'GetCapabilities'
                && ($query['VERSION'] ?? null) === '1.3.0';
        });
    }

    public function test_it_does_not_expose_urls_in_server_options(): void
    {
        $this->assertSame(
            ['approved_server' => 'Approved Server'],
            app(ApprovedWmsService::class)->serverOptions()
        );
    }

    public function test_it_rejects_a_server_that_is_not_on_the_allow_list(): void
    {
        Http::fake();
        $this->expectException(InvalidArgumentException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'https://attacker.example/wms',
            '1.3.0'
        );
    }

    public function test_it_rejects_an_unapproved_wms_version(): void
    {
        Http::fake();
        $this->expectException(InvalidArgumentException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '9.9.9'
        );
    }

    public function test_it_rejects_an_invalid_configured_wfs_version(): void
    {
        config(['wms_proxy.servers.approved_server.wfs_version' => '9.9.9']);
        Http::fake();
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }

    /**
     * @dataProvider unsafeSchemeProvider
     */
    public function test_it_rejects_alternate_protocols(string $unsafeUrl): void
    {
        config(['wms_proxy.servers.approved_server.wms_url' => $unsafeUrl]);
        Http::fake();
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }

    public function unsafeSchemeProvider(): array
    {
        return [
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://127.0.0.1/resource'],
            'ftp' => ['ftp://files.example.test/layers'],
            'dict' => ['dict://127.0.0.1:11211/stat'],
            'data' => ['data:text/plain,content'],
            'php' => ['php://filter/resource=index.php'],
            'ldap' => ['ldap://127.0.0.1/resource'],
        ];
    }

    public function test_it_rejects_query_parameters_in_configured_endpoint(): void
    {
        config([
            'wms_proxy.servers.approved_server.wms_url' =>
                'https://maps.example.test/geoserver/wms?path=/rest',
        ]);
        Http::fake();
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }

    public function test_it_rejects_redirect_responses(): void
    {
        Http::fake([
            '*' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1:8080/admin',
            ]),
        ]);
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }

    public function test_it_rejects_html_responses(): void
    {
        Http::fake([
            '*' => Http::response('<html>Not WMS</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }

    public function test_it_rejects_xml_that_is_not_wms_capabilities(): void
    {
        Http::fake([
            '*' => Http::response('<root>Not WMS</root>', 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);
        $this->expectException(RuntimeException::class);

        app(ApprovedWmsService::class)->fetchCapabilities(
            'approved_server',
            '1.3.0'
        );
    }
}
