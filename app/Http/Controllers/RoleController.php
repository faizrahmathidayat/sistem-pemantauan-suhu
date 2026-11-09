<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', ['roles' => Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('roles.create', ['role' => new Role, 'selected' => []]);
    }

    public function store(RoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $role = new Role(['name' => $request->validated('name')]);
        $permissions = $request->selectedPermissions();

        DB::transaction(function () use ($audit, $request, $role, $permissions): void {
            $audit->saveAndLog($role, $request->user(), 'role.created', $role->name, [
                'permissions' => ['from' => [], 'to' => $this->labels($permissions)],
            ], ['is_system']);
            $role->syncPermissions($permissions);
        });

        return redirect()->route('roles.index')->with('status', "Role {$role->name} ditambahkan.");
    }

    public function edit(Role $role): View
    {
        abort_if($role->is_system, 403);

        return view('roles.edit', ['role' => $role, 'selected' => $role->permissions()]);
    }

    public function update(RoleRequest $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $before = $role->permissions();
        $after = $request->selectedPermissions();
        $role->fill(['name' => $request->validated('name')]);

        $extra = [];
        if (! $this->sameSet($before, $after)) {
            $extra['permissions'] = ['from' => $this->labels($before), 'to' => $this->labels($after)];
        }

        DB::transaction(function () use ($audit, $request, $role, $after, $extra): void {
            $audit->saveAndLog($role, $request->user(), 'role.updated', $role->name, $extra, ['is_system']);
            $role->syncPermissions($after);
        });

        return redirect()->route('roles.index')->with('status', "Role {$role->name} disimpan.");
    }

    public function destroy(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $users = $role->users()->count();
        if ($users > 0) {
            return back()->with('error', "Role {$role->name} masih dipakai {$users} user. Pindahkan user ke role lain dulu.");
        }

        DB::transaction(function () use ($audit, $request, $role): void {
            $role->delete();
            $audit->record($request->user(), 'role.deleted', $role, $role->name, [
                'name' => ['from' => $role->name, 'to' => null],
            ]);
        });

        return redirect()->route('roles.index')->with('status', "Role {$role->name} dihapus.");
    }

    /**
     * @param  list<Permission>  $permissions
     * @return list<string>
     */
    private function labels(array $permissions): array
    {
        return array_map(fn (Permission $p) => $p->label(), $permissions);
    }

    /**
     * @param  list<Permission>  $a
     * @param  list<Permission>  $b
     */
    private function sameSet(array $a, array $b): bool
    {
        $values = fn (array $list) => collect($list)->map(fn (Permission $p) => $p->value)->sort()->values()->all();

        return $values($a) === $values($b);
    }
}
