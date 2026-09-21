<?php

namespace Tests\Feature\Fsm;

use App\Models\Fsm\ServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceProviderCreationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function add_form_exposes_uncovered_wards_through_an_info_popover(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Fsm/ServiceProviderController.php')
        );
        $service = file_get_contents(
            app_path('Services/Fsm/ServiceProviderService.php')
        );
        $view = file_get_contents(
            resource_path('views/fsm/service-providers/partial-form.blade.php')
        );

        $this->assertStringContainsString(
            'getUncoveredServiceAreaWards',
            $controller
        );
        $this->assertStringContainsString(
            'fsm.service_provider_wards',
            $service
        );
        $this->assertStringContainsString(
            "->where('status', true)",
            $service
        );
        $this->assertStringContainsString(
            'id="uncovered-service-areas-info"',
            $view
        );
        $this->assertStringContainsString(
            "trigger: 'focus'",
            $view
        );
        $this->assertStringContainsString(
            'All service areas are currently covered.',
            $view
        );
    }

    /** @test */
    public function it_stores_a_contract_document_and_multiple_service_area_wards(): void
    {
        Storage::fake('public');

        $uniqueValue = Str::lower(Str::random(12));
        $contractDocument = UploadedFile::fake()->create(
            'contract-document.pdf',
            100,
            'application/pdf'
        );

        $response = $this
            ->withoutMiddleware()
            ->post('/fsm/service-providers', [
                'company_name' => 'Service Provider ' . $uniqueValue,
                'email' => $uniqueValue . '@example.test',
                'ward' => 2,
                'company_location' => 'Test Address',
                'contact_person' => 'Test Contact',
                'contact_gender' => 'Male',
                'contact_number' => '9800000000',
                'contract_document_pdf' => $contractDocument,
                'service_area' => [7, 2, 4],
                'status' => 1,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/fsm/service-providers');

        $serviceProvider = ServiceProvider::query()
            ->where('email', $uniqueValue . '@example.test')
            ->firstOrFail();

        $this->assertSame('2,4,7', $serviceProvider->service_area);
        $this->assertNotNull($serviceProvider->contract_document_pdf);
        $this->assertEqualsCanonicalizing(
            [2, 4, 7],
            DB::table('fsm.service_provider_wards')
                ->where('service_provider_id', $serviceProvider->id)
                ->pluck('ward')
                ->map(function ($ward) {
                    return (int) $ward;
                })
                ->all()
        );

        $this->assertStringStartsWith(
            'contract_documents/',
            $serviceProvider->contract_document_pdf
        );

        Storage::disk('public')->assertExists(
            $serviceProvider->contract_document_pdf
        );
    }

    /** @test */
    public function it_updates_the_contract_document_and_multiple_service_area_wards(): void
    {
        Storage::fake('public');

        $uniqueValue = Str::lower(Str::random(12));
        $oldContractPath = 'contract_documents/old-contract.pdf';
        Storage::disk('public')->put($oldContractPath, 'old contract');

        $serviceProvider = new ServiceProvider();
        $serviceProvider->company_name = 'Existing Provider ' . $uniqueValue;
        $serviceProvider->email = $uniqueValue . '@example.test';
        $serviceProvider->ward = 1;
        $serviceProvider->company_location = 'Old Address';
        $serviceProvider->contact_person = 'Old Contact';
        $serviceProvider->contact_gender = 'Male';
        $serviceProvider->contact_number = '9800000000';
        $serviceProvider->contract_document_pdf = $oldContractPath;
        $serviceProvider->service_area = '1,2';
        $serviceProvider->status = 1;
        $serviceProvider->save();

        $newContractDocument = UploadedFile::fake()->create(
            'replacement-contract.pdf',
            100,
            'application/pdf'
        );

        $response = $this
            ->withoutMiddleware()
            ->patch('/fsm/service-providers/' . $serviceProvider->id, [
                'company_name' => $serviceProvider->company_name,
                'email' => $serviceProvider->email,
                'ward' => 3,
                'company_location' => 'Updated Address',
                'contact_person' => 'Updated Contact',
                'contact_gender' => 'Female',
                'contact_number' => '9811111111',
                'contract_document_pdf' => $newContractDocument,
                'service_area' => [9, 3, 6],
                'status' => 1,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/fsm/service-providers');


        $serviceProvider->refresh();

        $this->assertSame('3,6,9', $serviceProvider->service_area);
        $this->assertEqualsCanonicalizing(
            [3, 6, 9],
            DB::table('fsm.service_provider_wards')
                ->where('service_provider_id', $serviceProvider->id)
                ->pluck('ward')
                ->map(function ($ward) {
                    return (int) $ward;
                })
                ->all()
        );
        $this->assertNotSame(
            $oldContractPath,
            $serviceProvider->contract_document_pdf
        );
        $this->assertStringStartsWith(
            'contract_documents/',
            $serviceProvider->contract_document_pdf
        );

        Storage::disk('public')->assertMissing($oldContractPath);
        Storage::disk('public')->assertExists(
            $serviceProvider->contract_document_pdf
        );
    }
}
