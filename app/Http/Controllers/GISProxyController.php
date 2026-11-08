<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GISProxyController extends Controller
{
    public function wms(Request $request)
    {
        $params = $request->query();
        $service = strtoupper((string) ($params['SERVICE'] ?? $params['service'] ?? ''));
        $operation = strtoupper((string) ($params['REQUEST'] ?? $params['request'] ?? ''));
        abort_unless($service === 'WMS', 400, 'Invalid service.');
        abort_unless(in_array($operation, ['GETMAP', 'GETLEGENDGRAPHIC', 'GETFEATUREINFO'], true), 403, 'WMS operation is not allowed.');

        $raw = $operation === 'GETLEGENDGRAPHIC'
            ? ($params['LAYER'] ?? $params['layer'] ?? '')
            : ($params['QUERY_LAYERS'] ?? $params['query_layers'] ?? $params['LAYERS'] ?? $params['layers'] ?? '');
        foreach (array_filter(array_map('trim', explode(',', (string) $raw))) as $layer) {
            $this->authorizeLayer($request, $layer);
        }

        return $this->forward($params, '/wms', 60);
    }

    /**
     * Map tiles for the mobile app. Mobile roles do not hold the web map-layer
     * permissions, so access is limited to the gis.mobile_wms_layers pairs.
     * Only PNG images are returned, so no feature attributes reach the client.
     */
    public function mobileWms(Request $request)
    {
        $params = array_change_key_case($request->query(), CASE_UPPER);
        abort_unless(strtoupper((string) ($params['SERVICE'] ?? '')) === 'WMS', 400, 'Invalid service.');
        abort_unless(strtoupper((string) ($params['REQUEST'] ?? '')) === 'GETMAP', 403, 'WMS operation is not allowed.');
        abort_unless(($params['FORMAT'] ?? '') === 'image/png', 403, 'WMS format is not allowed.');
        abort_if(isset($params['SLD']) || isset($params['SLD_BODY']), 403, 'Custom styles are not allowed.');

        $layers = array_map('trim', explode(',', (string) ($params['LAYERS'] ?? '')));
        $styles = array_map('trim', explode(',', (string) ($params['STYLES'] ?? '')));
        abort_unless(count($layers) === count($styles), 403, 'Layer is not allowed.');
        foreach ($layers as $index => $layer) {
            $this->authorizeMobileLayer($layer, $styles[$index]);
        }

        // Forward the checked values, not the raw query, so a second spelling of a
        // parameter cannot reach GeoServer.
        return $this->forward($params, '/wms', 60);
    }

    public function wfs(Request $request)
    {
        $params = $request->query();
        $service = strtoupper((string) ($params['SERVICE'] ?? $params['service'] ?? ''));
        $operation = strtoupper((string) ($params['REQUEST'] ?? $params['request'] ?? ''));
        abort_unless($service === 'WFS' && $operation === 'GETFEATURE', 403, 'WFS operation is not allowed.');
        $typeName = $params['typeName'] ?? $params['TYPENAME'] ?? $params['typeNames'] ?? $params['TYPENAMES'] ?? null;
        abort_unless($typeName, 400, 'Layer is required.');
        foreach (array_filter(array_map('trim', explode(',', (string) $typeName))) as $layer) {
            $this->authorizeLayer($request, $layer);
        }
        return $this->forward($params, '/wfs', 60);
    }

    private function forward(array $params, string $endpoint, int $timeout)
    {
        $params['authkey'] = config('services.geoserver.auth_key');
        $response = Http::timeout($timeout)->get(rtrim(config('services.geoserver.url'), '/') . $endpoint, $params);
        $result = response($response->body(), $response->status());
        if ($response->hasHeader('Content-Disposition')) {
            $result->header('Content-Disposition', $response->header('Content-Disposition'));
        }
        return $result->header('Content-Type', $response->header('Content-Type', 'application/octet-stream'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizeMobileLayer(string $layer, string $style): void
    {
        $workspace = config('services.geoserver.workspace');
        $allowed = collect(config('gis.mobile_wms_layers'))->contains(
            fn ($pair) => $layer === "$workspace:{$pair['layer']}" && $style === "$workspace:{$pair['style']}"
        );
        abort_unless($allowed, 403, 'Layer is not allowed.');
    }

    private function authorizeLayer(Request $request, string $qualifiedLayer): void
    {
        [$workspace, $layer] = array_pad(explode(':', $qualifiedLayer, 2), 2, null);
        if ($layer === null) {
            $layer = $workspace;
        } elseif ($workspace !== config('services.geoserver.workspace')) {
            abort(403, 'Workspace is not allowed.');
        }
        $config = config("gis.layers.$layer");
        abort_unless($config, 403, 'Layer is not allowed.');
        if (!empty($config['permission'])) {
            abort_unless($request->user()->can($config['permission']), 403, 'You are not authorized for this layer.');
        }
    }
}
