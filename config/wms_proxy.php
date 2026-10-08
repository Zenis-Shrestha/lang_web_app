<?php

$geoServerBaseUrl = rtrim((string) env('GEOSERVER_URL', ''), '/');

return [
    'allowed_versions' => ['1.1.1', '1.3.0'],
    'connect_timeout_seconds' => 3,
    'timeout_seconds' => 10,
    'max_response_bytes' => 2 * 1024 * 1024,

    /*
     * Every reachable WMS server must be registered here. The browser sends
     * only the array key; it never supplies a scheme, host, port, or path.
     */
    'servers' => [
        'project_geoserver' => [
            'label' => 'Project GeoServer',
            'wms_url' => env(
                'WMS_PROJECT_WMS_URL',
                $geoServerBaseUrl !== '' ? $geoServerBaseUrl . '/wms' : ''
            ),
            'wfs_url' => env(
                'WMS_PROJECT_WFS_URL',
                $geoServerBaseUrl !== '' ? $geoServerBaseUrl . '/wfs' : ''
            ),
            'wfs_version' => '1.0.0',
            'workspace' => env('GEOSERVER_WORKSPACE', ''),
        ],
    ],
];
