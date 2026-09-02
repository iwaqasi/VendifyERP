<?php

namespace Tests\Feature\Api\V1;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Tests\TestCase;
use Tests\Concerns\CreatesPosWorld;

class AuthDevicesTest extends TestCase
{
    use CreatesPosWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosWorld();
        $this->licensed();
        $this->createPersonalAccessClient();
    }

    /** @test */
    public function login_issues_a_bearer_token_with_expiry(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->posUser->email,
            'password' => 'password',
            'device_name' => 'CI Test Terminal',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertNotNull($response->json('data.expires_at'));

        $this->assertEquals(
            1,
            Token::where('user_id', $this->posUser->id)
                ->where('name', 'CI Test Terminal')
                ->count()
        );
    }

    /** @test */
    public function devices_lists_active_sessions(): void
    {
        $this->createDeviceToken('Android Terminal A');
        $this->createDeviceToken('Android Terminal B');

        $response = $this->actingAsPosUser()
            ->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(
            ['Android Terminal A', 'Android Terminal B'],
            $names
        );
    }

    /** @test */
    public function a_device_session_can_be_revoked_remotely(): void
    {
        $token = $this->createDeviceToken('Lost Phone');

        $this->actingAsPosUser()
            ->deleteJson('/api/v1/auth/devices/'.$token->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue((bool) $token->fresh()->revoked);
    }

    /** @test */
    public function a_user_cannot_revoke_another_users_session(): void
    {
        $foreign = User::create([
            'first_name' => 'Foreign', 'last_name' => 'User',
            'username' => 'foreign_'.uniqid(), 'email' => uniqid().'@x.local',
            'password' => bcrypt('secret'),
        ]);

        $foreignToken = $this->createDeviceToken('Foreign Device', $foreign->id);

        $this->actingAsPosUser()
            ->deleteJson('/api/v1/auth/devices/'.$foreignToken->id)
            ->assertStatus(404);

        $this->assertFalse((bool) $foreignToken->fresh()->revoked);
    }

    /**
     * Passport's createToken() resolves the first personal access client.
     * Insert it (and the legacy lookup row when that table exists) so real
     * logins work inside the isolated test database.
     */
    private function createPersonalAccessClient(): void
    {
        $client = (new Client)->forceFill([
            'user_id' => null,
            'name' => 'Personal Access Client',
            'secret' => Str::random(40),
            'redirect' => 'http://localhost',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
        ]);
        $client->save();

        if (Schema::hasTable('oauth_personal_access_clients')) {
            DB::table('oauth_personal_access_clients')->insert([
                'client_id' => $client->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createDeviceToken(string $name, ?int $userId = null): Token
    {
        $token = (new Token)->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $userId ?? $this->posUser->id,
            'client_id' => Client::where('personal_access_client', true)->first()->id,
            'name' => $name,
            'revoked' => false,
            'expires_at' => now()->addDays(30),
        ]);
        $token->save();

        return $token;
    }
}
