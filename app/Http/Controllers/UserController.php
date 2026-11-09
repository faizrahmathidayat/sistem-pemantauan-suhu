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

    public function create(Request $request): View
    {
        return view('users.create', ['user' => new User, 'roles' => $this->assignableRoles($request)]);
    }

    public function store(UserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::findOrFail($data['role_id']);

        if ($role->is_system && ! $request->user()->isAdministrator()) {
            return back()->withInput()->withErrors(['role_id' => 'Hanya Administrator yang boleh memberi role Administrator.']);
        }

        $user = new User($data);
        $audit->saveAndLog($user, $request->user(), 'user.created', $user->name, [
            'role' => ['from' => null, 'to' => $role->name],
            'password' => ['from' => null, 'to' => 'diubah'],
        ], self::AUDIT_IGNORE);

        return redirect()->route('users.index')->with('status', "User {$user->name} ditambahkan.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureMayManage($request, $user);

        return view('users.edit', ['user' => $user, 'roles' => $this->assignableRoles($request)]);
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
        $user->fill(collect($data)->except($passwordChanged ? [] : ['password'])->all());

        $extra = [];
        if ($user->isDirty('role_id')) {
            $extra['role'] = ['from' => $oldRoleName, 'to' => $newRole->name];
        }
        if ($passwordChanged) {
            $extra['password'] = ['from' => null, 'to' => 'diubah'];
        }

        $audit->saveAndLog($user, $actor, 'user.updated', $user->name, $extra, self::AUDIT_IGNORE);

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

    /** Akun Administrator hanya boleh diubah atau dihapus oleh Administrator. */
    private function ensureMayManage(Request $request, User $target): void
    {
        abort_if($target->isAdministrator() && ! $request->user()->isAdministrator(), 403);
    }

    /** Role yang boleh dipilih pelaku: role sistem (Administrator) hanya ditawarkan kepada Administrator. */
    private function assignableRoles(Request $request)
    {
        return Role::query()
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->where('is_system', false))
            ->orderBy('name')
            ->get();
    }

    private function administratorCount(): int
    {
        return User::whereHas('role', fn ($role) => $role->where('is_system', true))->count();
    }
}
