<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman awal setelah login: halaman pertama yang diizinkan untuk user ini, atau
 * halaman "belum punya hak akses" bila tak ada satu pun (menghindari pengalihan berantai).
 */
class HomeController extends Controller
{
    /** @var array<string, string> izin => nama route, dalam urutan prioritas */
    private const LANDING = [
        'dashboard.view' => 'dashboard',
        'incident.view' => 'incidents.index',
        'notification.view' => 'notifications.index',
        'zone.view' => 'zones.index',
        'auditlog.view' => 'audit-logs.index',
        'warehouse.manage' => 'warehouses.index',
        'user.manage' => 'users.index',
        'role.manage' => 'roles.index',
    ];

    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        foreach (self::LANDING as $permission => $route) {
            if ($user->hasPermission(Permission::from($permission))) {
                return redirect()->route($route);
            }
        }

        return view('auth.no-access');
    }
}
