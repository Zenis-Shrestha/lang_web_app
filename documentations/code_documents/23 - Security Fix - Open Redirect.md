Version: V1.0.0  
Application: Base IMIS  
Document type: Security fix and code reference  
Source reviewed: 2026-09-23  
Branch: `security-fix-open-redirect`  
Security commit: `ce2c876` (`fix(security): restrict WMS proxy and block redirects`)

# Security Fix — Open Redirect in WMS Proxy

This document describes only the security changes introduced by the `security-fix-open-redirect` branch in commit `ce2c876`. The change secures the map module's Web Map Service (WMS) proxy by replacing a user-supplied destination URL with a server-side allow-list and by refusing upstream redirects.

Although the branch name refers to an open redirect, the affected code was a server-side proxy. Accepting an arbitrary URL and following redirects could allow an authenticated user to make the application server request an unintended external or internal resource. The fix therefore addresses both redirect-based abuse and server-side request forgery (SSRF) risk in this flow.

## Scope of Change

The security commit changes six files and does not add a database migration or seeder.

| File | Change |
| --- | --- |
| `app/Http/Controllers/MapsController.php` | Replaces arbitrary URL proxying with validated server selection and delegates outbound requests to `ApprovedWmsService`. |
| `app/Services/Maps/ApprovedWmsService.php` | Adds the WMS allow-list, endpoint validation, redirect blocking, response limits and XML validation. |
| `app/Services/Maps/MapsService.php` | Supplies approved WMS server identifiers and labels to the map view. |
| `config/wms_proxy.php` | Defines approved endpoints, versions, timeouts and maximum response size. |
| `resources/views/maps/index.blade.php` | Replaces free-text WMS URL entry with an approved-server selector and uses the approved response metadata. |
| `tests/Unit/ApprovedWmsServiceTest.php` | Adds unit coverage for approved requests and rejected unsafe inputs/responses. |

## Previous Risk

The earlier `proxyWms()` implementation accepted a `url` query parameter and sent an HTTP request to that address. It forwarded the remaining query parameters, disabled TLS verification and returned the remote response to the browser. Because the destination originated in the request, the application did not establish a reliable trust boundary around the remote host.

The main risks were:

- an authenticated user could choose the server-side request destination;
- the HTTP client could follow a redirect to a different destination;
- alternate destinations could include internal services reachable from the application server;
- TLS certificate verification was explicitly disabled;
- remote response type, size and document structure were not validated;
- low-level exception details could be returned to the client.

## Secured Request Flow

```text
Authenticated map user
        |
        | selects approved server ID and WMS version
        v
GET /proxy-wms?server={id}&version={version}
        |
        | auth middleware + 20 requests/minute throttle
        v
MapsController::proxyWms()
        |
        | validates request shape
        v
ApprovedWmsService::fetchCapabilities()
        |
        | resolves ID from config/wms_proxy.php
        | validates configured WMS/WFS endpoints and versions
        | sends GetCapabilities with redirects disabled
        | validates status, size, content type and XML root
        v
JSON response with capabilities and approved endpoint metadata
        |
        v
Map view lists named layers and loads the selected approved layer
```

The browser no longer sends a scheme, host, port or URL path to the proxy. It sends only a configuration key such as `project_geoserver` and a supported WMS version.

## Route and Access Control

The existing endpoint remains:

| Method | Route | Controller | Access controls |
| --- | --- | --- | --- |
| GET | `/proxy-wms` | `MapsController@proxyWms` | `auth` route group and `throttle:20,1` |

### Request Parameters

| Parameter | Required | Validation | Example |
| --- | --- | --- | --- |
| `server` | Yes | String matching `a-z`, `0-9`, underscore or hyphen | `project_geoserver` |
| `version` | Yes | String; service must also find it in the configured allow-list | `1.3.0` |

The controller returns HTTP 422 for an invalid or unapproved selection. Failures while contacting or validating an approved server return HTTP 502 with a generic message. The server-side warning log records the authenticated user ID, selected server ID and exception class without returning internal exception details to the browser.

### Successful Response

The controller returns JSON containing:

| Field | Purpose |
| --- | --- |
| `capabilities` | Validated WMS capabilities XML. |
| `version` | Approved WMS version used for the request. |
| `wms_url` | Configured WMS endpoint used for map tiles. |
| `wfs_url` | Configured WFS endpoint used to read selected feature geometry. |
| `wfs_version` | Configured and validated WFS version. |
| `workspace` | Optional configured GeoServer workspace. |

## Approved WMS Service

`App\Services\Maps\ApprovedWmsService` is the security boundary for the outbound capabilities request.

### Server Selection

`approvedServer()` accepts only a simple server identifier and looks up `wms_proxy.servers.{id}`. A missing entry or an entry without both `wms_url` and `wfs_url` is rejected. `serverOptions()` exposes only the identifier and display label to the server-selection field; it does not expose endpoint URLs in the initial option list.

### Endpoint Validation

Both configured endpoints must satisfy all of the following rules:

- the value must be a syntactically valid URL;
- the scheme must be `http` or `https`;
- a host must be present;
- embedded username or password values are not allowed;
- query strings and fragments are not allowed.

These checks apply to trusted configuration, not to a URL supplied by the browser. Alternative schemes such as `file`, `gopher`, `ftp`, `dict`, `data`, `php` and `ldap` are rejected.

### Outbound HTTP Controls

The service constructs the query itself with only `SERVICE=WMS`, `REQUEST=GetCapabilities` and the approved `VERSION`. It uses Laravel's HTTP client with:

- redirects disabled through `allow_redirects => false`;
- an explicit rejection if the upstream response is a redirect;
- a configurable connection timeout, defaulting to 3 seconds;
- a configurable total timeout, defaulting to 10 seconds;
- normal TLS verification; the earlier `verify => false` option is removed;
- a default maximum response body size of 2 MiB.

An empty, oversized or unsuccessful upstream response is rejected.

### XML Validation

The service accepts only these response media types, ignoring an optional charset:

- `application/xml`;
- `text/xml`;
- `application/vnd.ogc.wms_xml`.

The response is rejected when it contains a `DOCTYPE`. XML is parsed with `LIBXML_NONET`, and its root element must be either `WMS_Capabilities` or `WMT_MS_Capabilities`. These checks prevent an arbitrary HTML or unrelated XML response from being treated as WMS capabilities and reduce XML external-entity risk.

## Configuration

The allow-list is stored in `config/wms_proxy.php`.

| Setting | Default | Purpose |
| --- | --- | --- |
| `allowed_versions` | `1.1.1`, `1.3.0` | WMS versions accepted from the browser. |
| `connect_timeout_seconds` | `3` | Maximum connection-establishment time. |
| `timeout_seconds` | `10` | Maximum outbound request time. |
| `max_response_bytes` | `2097152` | Maximum accepted capabilities response size. |
| `servers` | `project_geoserver` | Server-side registry of approved WMS/WFS endpoints. |

The default server entry uses these environment variables:

| Variable | Use |
| --- | --- |
| `GEOSERVER_URL` | Base fallback used to construct `/wms` and `/wfs` endpoints. |
| `WMS_PROJECT_WMS_URL` | Optional explicit WMS endpoint. |
| `WMS_PROJECT_WFS_URL` | Optional explicit WFS endpoint. |
| `GEOSERVER_WORKSPACE` | Optional workspace added to an unqualified selected layer name. |

Every additional permitted server must be registered by a trusted deployer in the `servers` array with a stable identifier, label, WMS URL, WFS URL, supported WFS version and optional workspace. User-provided destinations must not be copied into this configuration automatically.

## Map User Interface Changes

The WMS modal now displays a select field populated from `ApprovedWmsService::serverOptions()` instead of accepting a free-text URL. When no valid approved server is configured, the selector and version control are disabled and the modal states that no approved server is available.

After a successful capabilities request, the browser:

1. parses the validated capabilities XML;
2. lists only named layers;
3. constructs the WFS feature request from the approved WFS URL, version and workspace returned by the application;
4. fetches the municipality boundary and selected feature data;
5. checks whether at least one selected feature intersects the municipality boundary;
6. adds the approved WMS tile layer when the intersection check succeeds.

The JavaScript uses `URL` and `searchParams` when constructing the selected WFS request. Error messages distinguish unavailable approved servers, invalid capabilities and layer-loading failures.

## Automated Test Coverage

`tests/Unit/ApprovedWmsServiceTest.php` uses Laravel HTTP fakes and covers:

| Test area | Expected result |
| --- | --- |
| Approved configuration | Builds a GetCapabilities request and returns configured WMS/WFS metadata. |
| Server options | Exposes IDs and labels without endpoint URLs. |
| Arbitrary server value or URL | Rejected because it is not an allow-listed identifier. |
| Unsupported WMS version | Rejected. |
| Invalid configured WFS version | Rejected. |
| Alternate URL schemes | `file`, `gopher`, `ftp`, `dict`, `data`, `php` and `ldap` are rejected. |
| Configured endpoint with query parameters | Rejected. |
| Redirect response | Rejected instead of followed. |
| HTML response | Rejected because the media type is not approved XML. |
| Unrelated XML | Rejected because the root is not a WMS capabilities element. |

Run the focused test with:

```bash
php artisan test --filter=ApprovedWmsServiceTest
```

## Deployment and Operations

No database migration or seeder is required for this security fix.

Before deployment:

1. Configure `GEOSERVER_URL`, or provide explicit `WMS_PROJECT_WMS_URL` and `WMS_PROJECT_WFS_URL` values.
2. Confirm the configured endpoints have no query string or fragment and use only HTTP or HTTPS.
3. Confirm the WMS server supports version 1.1.1 or 1.3.0 and returns a recognized XML content type.
4. Confirm the WFS endpoint supports the configured WFS version and browser CORS requirements.
5. Refresh Laravel configuration after environment changes using the deployment's normal `config:clear` or `config:cache` procedure.
6. Run the focused unit test and the manual verification checklist below.

Operational logs should be monitored for `Approved WMS capabilities request failed.` warnings. Repeated 422 or 502 responses may indicate invalid client selections, incorrect endpoint configuration, upstream availability problems or a blocked redirect.

## Verification Checklist

1. Sign in and open the map page; confirm the WMS modal shows only configured server labels.
2. Select each supported WMS version and confirm named layers are returned from the approved server.
3. Select a layer intersecting the municipality and confirm its WMS tiles are added.
4. Select a non-intersecting layer and confirm it is rejected in the browser.
5. Request `/proxy-wms` with an unknown server ID and confirm HTTP 422.
6. Request an unsupported version and confirm HTTP 422.
7. Configure a test endpoint that returns HTTP 301 or 302 and confirm the application returns HTTP 502 without following it.
8. Verify an HTML response, unrelated XML, empty response and oversized response are rejected.
9. Confirm the endpoint requires authentication and is rate-limited to 20 requests per minute.
10. Confirm normal TLS certificate validation is active in the deployment environment.

## Security Boundaries and Remaining Considerations

- The allow-list is a trusted administrative boundary. The service does not independently reject private IP addresses or resolve hostnames to classify their network range; a trusted deployer must not configure an unintended internal service.
- The successful JSON response includes approved WMS and WFS endpoint URLs because the browser uses them for tile and feature requests. Those endpoints must therefore be suitable for browser exposure and configured with appropriate CORS and access controls.
- The unit tests focus on `ApprovedWmsService`. Controller status codes, route middleware, rate limiting and the browser interaction should also be covered by integration or manual testing.
- The implementation rejects `DOCTYPE` and uses network-disabled XML parsing. This behavior should remain in place if XML parsing is refactored.
- Redirects must remain disabled even for approved hosts because a trusted URL can otherwise redirect to a different destination.

## Source Traceability

| Concern | Primary source |
| --- | --- |
| Request validation and response codes | `app/Http/Controllers/MapsController.php` |
| Allow-list and outbound request security | `app/Services/Maps/ApprovedWmsService.php` |
| Approved options passed to the view | `app/Services/Maps/MapsService.php` |
| Endpoint and limit configuration | `config/wms_proxy.php` |
| Server selector and layer-loading workflow | `resources/views/maps/index.blade.php` |
| Unit test scenarios | `tests/Unit/ApprovedWmsServiceTest.php` |
| Authentication and rate limiting | `routes/web.php` |

