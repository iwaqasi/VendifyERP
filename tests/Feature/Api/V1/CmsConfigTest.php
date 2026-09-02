<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\CreatesPosWorld;

class CmsConfigTest extends TestCase
{
    use CreatesPosWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosWorld();
        $this->licensed(pos: false, cms: true);

        $this->posBusiness->update(['slug' => 'acme']);

        DB::table('cms_settings')->insert([
            [
                'business_id' => $this->posBusiness->id,
                'key' => 'primary_color', 'value' => '#FF0000',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'business_id' => $this->posBusiness->id,
                'key' => 'phone', 'value' => '1234567890',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    /** @test */
    public function public_config_endpoint_resolves_a_tenant_by_slug(): void
    {
        $response = $this->getJson('/api/v1/cms/config?slug=acme')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals($this->posBusiness->id, $response->json('data.business_id'));
        $this->assertEquals('acme', $response->json('data.slug'));
        $this->assertEquals('#FF0000', $response->json('data.primary_color'));
        $this->assertEquals($this->posBusiness->name, $response->json('data.business_name'));
    }

    /** @test */
    public function public_config_endpoint_resolves_a_tenant_by_id(): void
    {
        $this->getJson('/api/v1/cms/config?business_id='.$this->posBusiness->id)
            ->assertOk()
            ->assertJsonPath('data.slug', 'acme');
    }

    /** @test */
    public function config_fails_closed_for_unknown_or_missing_tenant(): void
    {
        $this->getJson('/api/v1/cms/config?slug=does-not-exist')->assertStatus(404);
        $this->getJson('/api/v1/cms/config')->assertStatus(404);
    }
}
