<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_CHECKER], true)) {
            $role = '';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sortColumns = [
            'name' => 'name',
            'username' => 'username',
            'role' => 'role',
            'status' => 'is_active',
        ];

        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'name';
        }

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($builder) use ($search, $operator) {
                    $builder
                        ->where('name', $operator, "%{$search}%")
                        ->orWhere('username', $operator, "%{$search}%")
                        ->orWhere('role', $operator, "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $search,
            'role' => $role,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CHECKER])],
        ]);

        $data['is_active'] = true;
        User::create($data);

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_CHECKER])],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'User berhasil diperbarui.');
    }
}
