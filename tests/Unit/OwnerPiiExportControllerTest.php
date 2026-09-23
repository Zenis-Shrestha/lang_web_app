<?php

namespace Tests\Unit;

use App\Http\Controllers\BuildingInfo\OwnerPiiExportController;
use App\Models\User;
use App\Services\OwnerPiiAuditService;
use App\Services\OwnerPiiExportService;
use App\Services\PiiAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class OwnerPiiExportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pii.audit_enabled' => false]);

        Gate::before(function () {
            return true;
        });
    }

    public function test_wrong_password_blocks_export_and_revokes_access(): void
    {
        $user = $this->userWithPassword('correct-password');
        $request = $this->request($user, 'wrong-password');
        $access = app(PiiAccessService::class);
        $access->unlock(
            $user,
            'password_reentry',
            ['list', 'view'],
            PiiAccessService::ALL_OWNERS_RESOURCE_ID
        );
        $exporter = Mockery::mock(OwnerPiiExportService::class);
        $exporter->shouldNotReceive('parseCsvText');

        $controller = new OwnerPiiExportController(
            $exporter,
            app(OwnerPiiAuditService::class),
            $access
        );
        $controller->export($request);

        $this->assertTrue(
            $request->session()->get('errors')->has('export_password')
        );
        $this->assertFalse(
            $access->isUnlocked(
                $user,
                'list',
                PiiAccessService::ALL_OWNERS_RESOURCE_ID
            )
        );
    }

    public function test_correct_password_returns_a_no_store_csv_download(): void
    {
        $user = $this->userWithPassword('correct-password');
        $request = $this->request($user, 'correct-password');
        $exporter = Mockery::mock(OwnerPiiExportService::class);
        $exporter->shouldReceive('parseCsvText')
            ->once()
            ->with("bin\nB016739\n")
            ->andReturn(['B016739']);
        $exporter->shouldReceive('rowsForBins')
            ->once()
            ->with(['B016739'])
            ->andReturn([
                'rows' => [[
                    'B016739',
                    'Test Owner',
                    '9800000000',
                    'Male',
                    '12345678',
                    'found',
                ]],
                'requested_count' => 1,
                'exported_count' => 1,
                'missing_count' => 0,
            ]);
        $exporter->shouldReceive('headers')
            ->once()
            ->andReturn([
                'bin',
                'owner_name',
                'owner_contact',
                'owner_gender',
                'nid',
                'status',
            ]);

        $controller = new OwnerPiiExportController(
            $exporter,
            app(OwnerPiiAuditService::class),
            app(PiiAccessService::class)
        );
        $response = $controller->export($request);

        $this->assertSame(200, $response->getStatusCode());
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString(
            'owner-pii-export-',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_wrong_password_returns_json_for_async_export(): void
    {
        $user = $this->userWithPassword('correct-password');
        $request = $this->request($user, 'wrong-password');
        $request->headers->set('Accept', 'application/json');
        $exporter = Mockery::mock(OwnerPiiExportService::class);
        $exporter->shouldNotReceive('parseCsvText');

        $controller = new OwnerPiiExportController(
            $exporter,
            app(OwnerPiiAuditService::class),
            app(PiiAccessService::class)
        );
        $response = $controller->export($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Password confirmation failed.',
            $response->getData(true)['message']
        );
    }

    private function request(User $user, string $password): Request
    {
        $request = Request::create(
            '/building-info/owner-pii/export',
            'POST',
            [
                'export_password' => $password,
                'bin_csv' => "bin\nB016739\n",
            ]
        );
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->setLaravelSession($this->app['session']->driver());

        return $request;
    }

    private function userWithPassword(string $password): User
    {
        $user = new User();
        $user->setRawAttributes([
            'id' => 101,
            'password' => Hash::make($password),
        ]);

        return $user;
    }
}
