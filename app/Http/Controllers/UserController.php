<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Kolom yang dicatat lewat entri khusus (nama role, keterangan kata sandi), bukan diff mentah. */
    private const AUDIT_IGNORE = ['password', 'remember_token', 'role_id'];

    public function index(Request $request): View
    {
        $actor = $request->user();
        // null = Administrator melihat semua; selain itu hanya gudang miliknya yang boleh dibaca namanya.
        $visibleWarehouseIds = $actor->isAdministrator() ? null : $actor->warehouses()->pluck('warehouses.id')->all();

        $users = User::with(['role', 'warehouses'])->orderBy('name')->get();
        if ($visibleWarehouseIds !== null) {
            // User gudang lain disembunyikan; akun Administrator tetap tampil (tanpa tombol ubah/hapus).
            $users = $users->filter(fn (User $user) => $user->isAdministrator() || $this->mayManage($actor, $user, $visibleWarehouseIds))->values();
        }

        return view('users.index', ['users' => $users, 'visibleWarehouseIds' => $visibleWarehouseIds]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'user' => new User,
            'roles' => $this->assignableRoles($request),
            'warehouses' => $this->assignableWarehouses($request),
            'selectedWarehouses' => [],
        ]);
    }

    public function store(UserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::findOrFail($data['role_id']);

        if ($role->is_system && ! $request->user()->isAdministrator()) {
            return back()->withInput()->withErrors(['role_id' => 'Hanya Administrator yang boleh memberi role Administrator.']);
        }

        $user = new User(collect($data)->except('warehouses')->all());
        $warehouseIds = $this->requestedWarehouseIds($request);

        $extra = [
            'role' => ['from' => null, 'to' => $role->name],
            'password' => ['from' => null, 'to' => 'diubah'],
        ];
        if ($warehouseIds !== []) {
            $extra['warehouses'] = ['from' => [], 'to' => $this->warehouseNames($warehouseIds)];
        }

        DB::transaction(function () use ($audit, $request, $user, $extra, $warehouseIds): void {
            $audit->saveAndLog($user, $request->user(), 'user.created', $user->name, $extra, self::AUDIT_IGNORE);
            $user->warehouses()->sync($warehouseIds);
        });

        return redirect()->route('users.index')->with('status', "User {$user->name} ditambahkan.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureMayManage($request, $user);

        $assignable = $this->assignableWarehouses($request);

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles($request),
            'warehouses' => $assignable,
            'selectedWarehouses' => $user->warehouses()->whereIn('warehouses.id', $assignable->modelKeys())->pluck('warehouses.id')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->ensureMayManage($request, $user);

        $data = $request->validated();
        $newRole = Role::findOrFail($data['role_id']);
        $actor = $request->user();

        if (! $actor->isAdministrator()) {
            // Tanpa ini, pemegang user.manage bisa menaikkan haknya sendiri menjadi Administrator
            // atau berpindah ke role yang lebih kuat.
            if ($newRole->is_system) {
                return back()->withInput()->withErrors(['role_id' => 'Hanya Administrator yang boleh memberi role Administrator.']);
            }
            if ($user->is($actor) && (int) $data['role_id'] !== (int) $user->role_id) {
                return back()->withInput()->withErrors(['role_id' => 'Anda tidak bisa mengubah role Anda sendiri.']);
            }
        }

        if ($user->isAdministrator() && ! $newRole->is_system && $this->administratorCount() <= 1) {
            return back()->withInput()->withErrors(['role_id' => 'Administrator terakhir tidak bisa dipindah ke role lain.']);
        }

        $oldRoleName = $user->role?->name;
        $passwordChanged = filled($data['password'] ?? null);
        $user->fill(collect($data)->except(array_merge(['warehouses'], $passwordChanged ? [] : ['password']))->all());

        $currentIds = $user->warehouses()->pluck('warehouses.id')->all();
        $newIds = $this->mergedAssignment($actor, $currentIds, $this->requestedWarehouseIds($request));

        $extra = [];
        if ($user->isDirty('role_id')) {
            $extra['role'] = ['from' => $oldRoleName, 'to' => $newRole->name];
        }
        if ($passwordChanged) {
            $extra['password'] = ['from' => null, 'to' => 'diubah'];
        }
        if ($this->sorted($currentIds) !== $this->sorted($newIds)) {
            $extra['warehouses'] = ['from' => $this->warehouseNames($currentIds), 'to' => $this->warehouseNames($newIds)];
        }

        DB::transaction(function () use ($audit, $actor, $user, $extra, $newIds): void {
            $audit->saveAndLog($user, $actor, 'user.updated', $user->name, $extra, self::AUDIT_IGNORE);
            $user->warehouses()->sync($newIds);
        });

        return redirect()->route('users.index')->with('status', "User {$user->name} disimpan.");
    }

    public function destroy(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->ensureMayManage($request, $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }
        if ($user->isAdministrator() && $this->administratorCount() <= 1) {
            return back()->with('error', 'Administrator terakhir tidak bisa dihapus.');
        }

        DB::transaction(function () use ($audit, $request, $user): void {
            $audit->record($request->user(), 'user.deleted', $user, $user->name, [
                'email' => ['from' => $user->email, 'to' => null],
                'role' => ['from' => $user->role?->name, 'to' => null],
            ]);
            $user->delete();
        });

        return redirect()->route('users.index')->with('status', "User {$user->name} dihapus.");
    }

    /**
     * Administrator boleh mengelola semua user. User lain hanya boleh mengelola user yang gudangnya
     * seluruhnya termasuk gudang miliknya (atau yang belum punya gudang), dan tidak pernah akun
     * Administrator. Tanpa batas ini, pemegang user.manage di satu gudang bisa mengganti kata sandi
     * user gudang lain lalu masuk sebagai dia.
     *
     * @param  list<int>|null  $actorWarehouseIds  isi bila sudah dihitung (hindari query berulang)
     */
    private function mayManage(User $actor, User $target, ?array $actorWarehouseIds = null): bool
    {
        if ($actor->isAdministrator()) {
            return true;
        }
        if ($target->isAdministrator()) {
            return false;
        }

        $allowed = $actorWarehouseIds ?? $actor->warehouses()->pluck('warehouses.id')->all();

        return $target->warehouses->pluck('id')->diff($allowed)->isEmpty();
    }

    private function ensureMayManage(Request $request, User $target): void
    {
        abort_unless($this->mayManage($request->user(), $target), 403);
    }

    /** Role yang boleh dipilih pelaku: role sistem (Administrator) hanya ditawarkan kepada Administrator. */
    private function assignableRoles(Request $request)
    {
        return Role::query()
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->where('is_system', false))
            ->orderBy('name')
            ->get();
    }

    /**
     * Gudang yang boleh ditetapkan pelaku: semua untuk Administrator, selain itu gudang miliknya.
     *
     * @return Collection<int, Warehouse>
     */
    private function assignableWarehouses(Request $request): Collection
    {
        return Warehouse::visibleTo($request->user())->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    private function requestedWarehouseIds(Request $request): array
    {
        return array_values(array_unique(array_map('intval', $request->validated('warehouses') ?? [])));
    }

    /**
     * Administrator mengganti seluruh daftar; user lain hanya mengatur gudang miliknya dan tidak
     * menyentuh gudang target yang tidak bisa ia lihat.
     *
     * @param  list<int>  $current
     * @param  list<int>  $requested
     * @return list<int>
     */
    private function mergedAssignment(User $actor, array $current, array $requested): array
    {
        if ($actor->isAdministrator()) {
            return $requested;
        }

        $allowed = $actor->warehouses()->pluck('warehouses.id')->all();

        return array_values(array_unique(array_merge(array_diff($current, $allowed), $requested)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function warehouseNames(array $ids): array
    {
        return Warehouse::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return array_values($ids);
    }

    private function administratorCount(): int
    {
        return User::whereHas('role', fn ($role) => $role->where('is_system', true))->count();
    }
}
