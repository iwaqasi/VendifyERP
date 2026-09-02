<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Tests\Concerns\CreatesPosWorld;

class LicenseTest extends TestCase
{
    use CreatesPosWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosWorld();
    }

    /** @test */
    public function guest_cannot_check_license(): void
    {
        $this->getJson('/api/v1/license/check')
            ->assertStatus(401);
    }

    /** @test */
    public function license_is_denied_without_subscription(): void
    {
        $this->actingAsPosUser()
            ->getJson('/api/v1/license/check')
            ->assertOk()
            ->assertJsonPath('data.has_pos_license', false)
            ->assertJsonPath('data.has_cms_license', false)
            ->assertJsonPath('data.error_code', 'NO_SUBSCRIPTION');
    }

    /** @test */
    public function license_is_granted_with_pos_package(): void
    {
        $this->licensed();

        $this->actingAsPosUser()
            ->getJson('/api/v1/license/check')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_pos_license', true)
            ->assertJsonPath('data.has_cms_license', true);
    }

    /** @test */
    public function license_denies_pos_when_package_lacks_it(): void
    {
        $this->licensed(pos: false, cms: true);

        $this->actingAsPosUser()
            ->getJson('/api/v1/license/check')
            ->assertOk()
            ->assertJsonPath('data.has_pos_license', false)
            ->assertJsonPath('data.has_cms_license', true);
    }

    /** @test */
    public function pos_sale_is_blocked_without_subscription(): void
    {
        // No licensed() — middleware must fail closed with 403.
        $this->postSale($this->salePayload())
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'NO_SUBSCRIPTION');
    }

    /** @test */
    public function pos_sale_is_blocked_when_package_lacks_pos(): void
    {
        $this->licensed(pos: false);

        $this->postSale($this->salePayload())
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'POS_NOT_INCLUDED');
    }

    /** @test */
    public function pos_sale_passes_license_gate_with_valid_subscription(): void
    {
        $this->licensed();

        // Reach the controller: validation errors (not 403) prove the gate passed.
        $this->postSale(['location_id' => $this->posLocation->id])
            ->assertStatus(422);
    }
}
