<?php

namespace Tests\Unit;

use App\Services\ApplicationCustomerPiiExportService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationCustomerPiiExportServiceTest extends TestCase
{
    public function test_it_parses_and_deduplicates_application_ids(): void
    {
        $service = app(ApplicationCustomerPiiExportService::class);

        $ids = $service->parseCsvText("application_id\n12\n15\n12\n");

        $this->assertSame(['12', '15'], $ids);
    }

    public function test_it_requires_the_application_id_header(): void
    {
        $service = app(ApplicationCustomerPiiExportService::class);

        $this->expectException(ValidationException::class);
        $service->parseCsvText("bin\nB016742\n");
    }

    public function test_it_rejects_non_numeric_application_ids(): void
    {
        $service = app(ApplicationCustomerPiiExportService::class);

        $this->expectException(ValidationException::class);
        $service->parseCsvText("application_id\nAPP-12\n");
    }
}
