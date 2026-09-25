<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Models\UserWarehouseAssignment;
use App\Services\ErpCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_CHECKER,
        User::ROLE_GERAI,
        User::ROLE_ADMIN_GERAI,
        User::ROLE_CHECKER_GERAI,
        User::ROLE_ADMIN_GUDANG,
        User::ROLE_CHECKER_GUDANG,
    ];

    private const GERAI_ROLES = [
        User::ROLE_GERAI,
        User::ROLE_ADMIN_GERAI,
        User::ROLE_CHECKER_GERAI,
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $role = strtoupper(trim((string) $request->input('role', '')));
        $status = strtolower(trim((string) $request->input('status', '')));
        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->input('per_page', 25);

        if (! in_array($role, self::ROLES, true)) $role = '';
        if (! in_array($status, ['active', 'inactive'], true)) $status = '';
        if (! in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;

        $sortColumns = ['name'=>'name', 'username'=>'username', 'role'=>'role', 'status'=>'is_active'];
        if (! array_key_exists($sort, $sortColumns)) $sort = 'name';

        $users = User::query()
            ->with('warehouseAssignments')
            ->when($search !== '', function ($query) use ($search) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($builder) use ($search, $operator) {
                    $builder->where('name', $operator, "%{$search}%")
                        ->orWhere('username', $operator, "%{$search}%")
                        ->orWhere('warehouse_code', $operator, "%{$search}%")
                        ->orWhere('warehouse_name', $operator, "%{$search}%")
                        ->orWhereHas('warehouseAssignments', function ($assignment) use ($search, $operator) {
                            $assignment->where('warehouse_code', $operator, "%{$search}%")
                                ->orWhere('warehouse_name', $operator, "%{$search}%")
                                ->orWhere('source_database', $operator, "%{$search}%");
                        });
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
            'role' => ['required', Rule::in(self::ROLES)],
            'warehouse_assignments' => ['nullable','array','max:100'],
            'warehouse_assignments.*' => ['string','max:100'],
        ]);

        $assignments = $this->resolveAssignments(
            $data['role'],
            $data['warehouse_assignments'] ?? [],
            $erp
        );
        unset($data['warehouse_assignments']);

        DB::transaction(function () use ($data, $assignments): void {
            $legacy = $this->legacyBinding($assignments);
            $user = User::create(array_merge($data, $legacy, ['is_active'=>true]));
            $this->replaceAssignments($user, $assignments);
        });

        return back()->with('success', 'User berhasil dibuat. Assignment gudang Gerai juga sudah disimpan.');
    }

    public function update(Request $request, User $user, ErpCatalogService $erp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'username' => ['required','string','max:100','alpha_dash',Rule::unique('users','username')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'warehouse_assignments' => ['nullable','array','max:100'],
            'warehouse_assignments.*' => ['string','max:100'],
            'is_active' => ['nullable','boolean'],
            'password' => ['nullable','string','min:6'],
        ]);

        $assignments = $this->resolveAssignments(
            $data['role'],
            $data['warehouse_assignments'] ?? [],
            $erp
        );
        unset($data['warehouse_assignments']);

        $data['is_active'] = $request->boolean('is_active');
        if (empty($data['password'])) unset($data['password']);

        DB::transaction(function () use ($user, $data, $assignments): void {
            $user->update(array_merge($data, $this->legacyBinding($assignments)));
            $this->replaceAssignments($user, $assignments);
        });

        return back()->with('success', 'User berhasil diperbarui. Assignment multi database / multi gudang juga sudah diperbarui.');
    }

    /**
     * @param array<int,string> $keys
     * @return array<int,array{source_database:string,erp_warehouse_id:int,warehouse_code:string,warehouse_name:string}>
     */
    private function resolveAssignments(string $role, array $keys, ErpCatalogService $erp): array
    {
        if (! in_array($role, self::GERAI_ROLES, true)) {
            return [];
        }

        $keys = collect($keys)
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            throw ValidationException::withMessages([
                'warehouse_assignments' => 'Pilih minimal satu gudang untuk Admin Gerai / Checker Gerai.',
            ]);
        }

        $requested = [];
        foreach ($keys as $key) {
            [$source, $warehouseId] = array_pad(explode('|', $key, 2), 2, null);
            $source = strtoupper(trim((string) $source));
            $warehouseId = (int) $warehouseId;
            if (! in_array($source, [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI], true) || $warehouseId <= 0) {
                throw ValidationException::withMessages(['warehouse_assignments' => 'Assignment gudang tidak valid: '.$key]);
            }
            $requested[$source][$warehouseId] = true;
        }

        $resolved = [];
        foreach ($requested as $source => $ids) {
            $catalog = collect($erp->warehouses($source))->keyBy(fn (array $row) => (int) $row['warehouse_id']);
            foreach (array_keys($ids) as $warehouseId) {
                $warehouse = $catalog->get((int) $warehouseId);
                if (! $warehouse) {
                    throw ValidationException::withMessages([
                        'warehouse_assignments' => "Gudang {$source} / {$warehouseId} tidak ditemukan atau sudah nonaktif di ERP.",
                    ]);
                }
                $resolved[] = [
                    'source_database' => $source,
                    'erp_warehouse_id' => (int) $warehouse['warehouse_id'],
                    'warehouse_code' => (string) $warehouse['warehouse_code'],
                    'warehouse_name' => (string) $warehouse['warehouse_name'],
                ];
            }
        }

        usort($resolved, fn ($a, $b) => [$a['source_database'],$a['warehouse_code']] <=> [$b['source_database'],$b['warehouse_code']]);
        return $resolved;
    }

    /** @param array<int,array<string,mixed>> $assignments */
    private function replaceAssignments(User $user, array $assignments): void
    {
        $user->warehouseAssignments()->delete();
        foreach ($assignments as $assignment) {
            $user->warehouseAssignments()->create($assignment);
        }
    }

    /** @param array<int,array<string,mixed>> $assignments */
    private function legacyBinding(array $assignments): array
    {
        $first = $assignments[0] ?? null;
        return [
            'source_database' => $first['source_database'] ?? null,
            'erp_warehouse_id' => $first['erp_warehouse_id'] ?? null,
            'warehouse_code' => $first['warehouse_code'] ?? null,
            'warehouse_name' => $first['warehouse_name'] ?? null,
        ];
    }
}
