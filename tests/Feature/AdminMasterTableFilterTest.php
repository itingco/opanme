<?php

namespace Tests\Feature;

use App\Models\UomRatio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMasterTableFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_table_can_filter_by_role_status_and_search_with_page_size_control(): void
    {
        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'username' => 'systemadmin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Alpha Checker',
            'username' => 'alpha.checker',
            'role' => User::ROLE_CHECKER,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Beta Checker',
            'username' => 'beta.checker',
            'role' => User::ROLE_CHECKER,
            'is_active' => false,
        ]);

        User::factory()->create([
            'name' => 'Alpha Admin',
            'username' => 'alpha.admin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'q' => 'Alpha',
            'role' => User::ROLE_CHECKER,
            'status' => 'active',
            'sort' => 'username',
            'direction' => 'desc',
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertSee('Alpha Checker')
            ->assertDontSee('Beta Checker')
            ->assertDontSee('Alpha Admin')
            ->assertSee('name="role"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('data-sort="username"', false);
    }

    public function test_ratio_table_can_filter_database_uom_search_and_exposes_sort_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        UomRatio::create([
            'source_database' => 'AS_INGCO',
            'item_id' => 101,
            'item_code' => 'DRILL-01',
            'item_name' => 'Cordless Drill',
            'uom_level' => 2,
            'uom_code' => 'BOX',
            'ratio' => 20,
        ]);

        UomRatio::create([
            'source_database' => 'AS_INGCO',
            'item_id' => 102,
            'item_code' => 'DRILL-02',
            'item_name' => 'Cordless Drill Small',
            'uom_level' => 1,
            'uom_code' => 'PCS',
            'ratio' => 1,
        ]);

        UomRatio::create([
            'source_database' => 'AS_SMI',
            'item_id' => 103,
            'item_code' => 'DRILL-SMI',
            'item_name' => 'Cordless Drill SMI',
            'uom_level' => 2,
            'uom_code' => 'BOX',
            'ratio' => 12,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ratios.index', [
            'q' => 'Drill',
            'source_database' => 'AS_INGCO',
            'uom_level' => 2,
            'sort' => 'ratio',
            'direction' => 'desc',
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertSee('DRILL-01')
            ->assertDontSee('DRILL-02')
            ->assertDontSee('DRILL-SMI')
            ->assertSee('name="uom_level"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('data-sort="ratio"', false);
    }
}
