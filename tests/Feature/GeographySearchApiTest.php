<?php

namespace Tests\Feature;

use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GeographySearchApiTest extends TestCase
{
    use DatabaseTransactions;

    protected Municipality $municipality;
    protected SubMunicipality $subMunicipality;
    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $uniq = uniqid();
        $this->municipality = Municipality::create([
            'name' => 'بلدية طرابلس المركز ' . $uniq,
        ]);

        $this->subMunicipality = SubMunicipality::create([
            'municipality_id' => $this->municipality->id,
            'name'            => 'بلدية النوفليين الفرعية ' . $uniq,
        ]);

        $this->zone = Zone::create([
            'sub_municipality_id' => $this->subMunicipality->id,
            'name'                => 'منطقة بن عاشور ' . $uniq,
        ]);
    }

    public function test_it_successfully_searches_municipalities_when_found()
    {
        $response = $this->json('GET', '/api/geography/search', [
            'search_keyword' => $this->municipality->name,
            'type'           => 'municipality',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'تم العثور على النتائج',
            ])
            ->assertJsonFragment([
                'id'   => $this->municipality->id,
                'name' => $this->municipality->name,
            ]);
    }

    public function test_it_returns_custom_error_message_when_municipality_not_found()
    {
        $response = $this->json('GET', '/api/geography/search', [
            'search_keyword' => 'بلدية_غير_موجودة_نهائيا_xyz_999999',
            'type'           => 'municipality',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'error',
                'message' => 'لا توجد بلدية بهذا الاسم',
                'data'    => [],
            ]);
    }

    public function test_it_successfully_searches_sub_municipalities_when_found()
    {
        $response = $this->json('POST', '/api/geography/search', [
            'search_keyword' => $this->subMunicipality->name,
            'type'           => 'sub_municipality',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'تم العثور على النتائج',
            ])
            ->assertJsonFragment([
                'id'              => $this->subMunicipality->id,
                'name'            => $this->subMunicipality->name,
                'municipality_id' => $this->municipality->id,
            ]);
    }

    public function test_it_returns_custom_error_message_when_sub_municipality_not_found()
    {
        $response = $this->json('POST', '/api/geography/search', [
            'search_keyword' => 'بلدية_فرعية_غير_موجودة_xyz_888888',
            'type'           => 'sub_municipality',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'error',
                'message' => 'لا توجد بلدية فرعية بهذا الاسم',
                'data'    => [],
            ]);
    }

    public function test_it_successfully_searches_regions_when_found()
    {
        $response = $this->json('GET', '/api/geography/search', [
            'search_keyword' => $this->zone->name,
            'type'           => 'region',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'تم العثور على النتائج',
            ])
            ->assertJsonFragment([
                'id'                  => $this->zone->id,
                'name'                => $this->zone->name,
                'sub_municipality_id' => $this->subMunicipality->id,
            ]);
    }

    public function test_it_returns_custom_error_message_when_region_not_found()
    {
        $response = $this->json('GET', '/api/geography/search', [
            'search_keyword' => 'منطقة_غير_موجودة_xyz_777777',
            'type'           => 'region',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'error',
                'message' => 'لا توجد منطقة بهذا الاسم',
                'data'    => [],
            ]);
    }

    public function test_it_validates_required_parameters()
    {
        $response = $this->json('POST', '/api/geography/search', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'data'   => [],
            ]);
    }

    public function test_it_validates_invalid_type()
    {
        $response = $this->json('POST', '/api/geography/search', [
            'search_keyword' => 'طرابلس',
            'type'           => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'data'   => [],
            ]);
    }
}
