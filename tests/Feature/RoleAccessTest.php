<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_checker_cannot_open_admin_dashboard(): void
    {
        $checker = User::factory()->create(['role' => User::ROLE_CHECKER]);

        $this->actingAs($checker)
            ->get('/admin')
            ->assertForbidden();
    }
}
