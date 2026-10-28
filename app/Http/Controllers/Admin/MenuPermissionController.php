<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MenuPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class MenuPermissionController extends Controller
{
    public function index(Request $request, MenuPermissionService $permissions): View
    {
        abort_unless($request->user()?->isAdmin(), 403);
        return view('admin.menu-permissions', [
            'roles' => config('menu_permissions.roles'),
            'menus' => config('menu_permissions.menus'),
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(Schema::hasTable('role_menu_permissions'), 503, 'Jalankan php artisan migrate terlebih dahulu.');
        $data = $request->validate([
            'access' => ['nullable','array'],
            'access.*' => ['nullable','array'],
            'access.*.*' => ['in:1'],
        ]);
        $roles = config('menu_permissions.roles');
        $menus = array_keys(config('menu_permissions.menus'));
        // Always save complete matrix: unchecked boxes become FALSE.
        DB::transaction(function () use ($roles, $menus, $data) {
            foreach ($roles as $role) {
                foreach ($menus as $menu) {
                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role'=>$role, 'menu_key'=>$menu],
                        ['allowed'=>isset($data['access'][$role][$menu]), 'updated_at'=>now()]
                    );
                }
            }
        });
        return redirect()->route('admin.menu-permissions.index')->with('success','Hak akses menu per role berhasil disimpan.');
    }
}
