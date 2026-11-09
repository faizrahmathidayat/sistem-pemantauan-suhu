<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Kolom yang dicatat lewat entri khusus (nama role, keterangan kata sandi), bukan diff mentah. */
    private const AUDIT_IGNORE = ['password', 'remember_token', 'role_id'];

    public function index(): View
    {
        return view('users.index', ['users' => User::with('role')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User, 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(UserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $user = new User($data);
        $role = Role::findOrFail($data['role_id']);

        $audit->saveAndLog($user, $request->user(), 'user.created', $user->name, [
            'role' => ['from' => null, 'to' => $role->name],
            'password' => ['from' => null, 'to' => 'diubah'],
        ], self::AUDIT_IGNORE);

        return redirect()->route('users.index')->with('status', "User {$user->name} ditambahkan.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $newRole = Role::findOrFail($data['role_id']);

        if ($user->isAdministrator() && ! $newRole->is_system && $this->administratorCount() <= 1) {
            return back()->withInput()->withErrors(['role_id' => 'Administrator terakhir tidak bisa dipindah ke role lain.']);
        }

        $oldRoleName = $user->role?->name;
        $passwordChanged = filled($data['password'] ?? null);
        $user->fill(collect($data)->except($passwordChanged ? [] : ['password'])->all());

        $extra = [];
        if ($user->isDirty('role_id')) {
            $extra['role'] = ['from' => $oldRoleName, 'to' => $newRole->name];
        }
        if ($passwordChanged) {
            $extra['password'] = ['from' => null, 'to' => 'diubah'];
        }

        $audit->saveAndLog($user, $request->user(), 'user.updated', $user->name, $extra, self::AUDIT_IGNORE);

        return redirect()->route('users.index')->with('status', "User {$user->name} disimpan.");
    }

    public function destroy(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
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

    private function administratorCount(): int
    {
        return User::whereHas('role', fn ($role) => $role->where('is_system', true))->count();
    }
}
