<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Services\ErpCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $role = strtoupper(trim((string) $request->input('role', '')));
        $status = strtolower(trim((string) $request->input('status', '')));
        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 25);

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_CHECKER, User::ROLE_GERAI], true)) {
            $role = '';
        }
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sortColumns = ['name'=>'name', 'username'=>'username', 'role'=>'role', 'status'=>'is_active'];
        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'name';
        }

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($builder) use ($search, $operator) {
                    $builder->where('name', $operator, "%{$search}%")
                        ->orWhere('username', $operator, "%{$search}%")
                        ->orWhere('warehouse_code', $operator, "%{$search}%")
                        ->orWhere('warehouse_name', $operator, "%{$search}%");
                });
            })
            ->when($role !== '', fn ($q) => $q->where('role', $role))
            ->when($status !== '', fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.users.index', compact('users','search','role','status','sort','direction','perPage'));
    }

    public function store(Request $request, ErpCatalogService $erp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'username' => ['required','string','max:100','alpha_dash',Rule::unique('users','username')],
            'password' => ['required','string','min:6'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CHECKER, User::ROLE_GERAI])],
            'source_database' => ['nullable', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'erp_warehouse_id' => ['nullable','integer'],
        ]);

        $binding = $this->resolveBinding($data['role'], $data['source_database'] ?? null, $data['erp_warehouse_id'] ?? null, $erp);
        User::create(array_merge($data, $binding, ['is_active'=>true]));

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function update(Request $request, User $user, ErpCatalogService $erp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'username' => ['required','string','max:100','alpha_dash',Rule::unique('users','username')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CHECKER, User::ROLE_GERAI])],
            'source_database' => ['nullable', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'erp_warehouse_id' => ['nullable','integer'],
            'is_active' => ['nullable','boolean'],
            'password' => ['nullable','string','min:6'],
        ]);

        $binding = $this->resolveBinding($data['role'], $data['source_database'] ?? null, $data['erp_warehouse_id'] ?? null, $erp);
        $data['is_active'] = $request->boolean('is_active');
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update(array_merge($data, $binding));

        return back()->with('success', 'User berhasil diperbarui.');
    }

    private function resolveBinding(string $role, ?string $sourceDatabase, ?int $warehouseId, ErpCatalogService $erp): array
    {
        $empty = ['source_database'=>null, 'erp_warehouse_id'=>null, 'warehouse_code'=>null, 'warehouse_name'=>null];
        if ($role !== User::ROLE_GERAI) {
            return $empty;
        }
        if ($warehouseId === null) {
            return $empty; // GERAI tanpa binding = bebas pilih saat membuat sampling cycle.
        }
        if (! $sourceDatabase) {
            throw ValidationException::withMessages(['source_database'=>'Database ERP wajib dipilih jika User Gerai dilekatkan ke gudang.']);
        }

        $warehouse = collect($erp->warehouses($sourceDatabase))->firstWhere('warehouse_id', $warehouseId);
        if (! $warehouse) {
            throw ValidationException::withMessages(['erp_warehouse_id'=>'Gudang tidak valid atau sudah nonaktif di ERP.']);
        }

        return [
            'source_database' => $sourceDatabase,
            'erp_warehouse_id' => (int) $warehouse['warehouse_id'],
            'warehouse_code' => $warehouse['warehouse_code'],
            'warehouse_name' => $warehouse['warehouse_name'],
        ];
    }
}
