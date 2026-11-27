<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role, array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['password' => 'secret123', 'is_active' => true, 'owner_type' => 'system', 'owner_id' => 0, 'must_change_password' => false], $attrs));
        $u->assignRole($role);

        return $u;
    }

    public function test_delivery_agent_gets_token_and_can_use_it(): void
    {
        $u = $this->user('branch-delivery');

        $res = $this->postJson('/api/driver/login', ['email' => $u->email, 'password' => 'secret123']);
        $res->assertOk()->assertJsonStructure(['token', 'user', 'must_change_password']);

        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('id', $u->id);
        $this->withToken($res->json('token'))->getJson('/api/delivery/shipments')->assertOk();
    }

    public function test_non_delivery_roles_are_refused(): void
    {
        $u = $this->user('branch-admin');

        $this->postJson('/api/driver/login', ['email' => $u->email, 'password' => 'secret123'])->assertForbidden();
    }

    public function test_bad_password_and_inactive_are_refused(): void
    {
        $u = $this->user('branch-delivery');
        $this->postJson('/api/driver/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnauthorized();

        $u->update(['is_active' => false]);
        $this->postJson('/api/driver/login', ['email' => $u->email, 'password' => 'secret123'])->assertForbidden();
    }

    public function test_logout_revokes_token(): void
    {
        $u = $this->user('branch-delivery');
        $token = $this->postJson('/api/driver/login', ['email' => $u->email, 'password' => 'secret123'])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
