<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateUserPermissionsRequest;
use App\Models\RolePermission;
use App\Models\UserPermissionOverride;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    private function assertCanManage(Request $request): void
    {
        $role = $request->user()->role;
        if (!in_array($role, ['superadmin', 'admin_branch'])) {
            abort(403, 'Hanya superadmin dan admin_branch yang dapat mengelola permission.');
        }
    }

    public function index(Request $request)
    {
        $this->assertCanManage($request);

        $permissions = RolePermission::where('tenant_id', $request->user()->tenant_id)->get();

        $roles = [
            'superadmin', 'admin_branch', 'finance', 'cs', 'supervisor', 'driver_tetap', 'teknisi'
        ];

        $data = [];
        foreach ($roles as $role) {
            $data[$role] = $permissions->where('role', $role)->pluck('permission_key')->values()->toArray();
        }

        return response()->json(['data' => $data]);
    }

    public function update(UpdateRolePermissionsRequest $request, string $role)
    {
        $this->assertCanManage($request);

        $authRole = $request->user()->role;

        // admin_branch tidak boleh edit role superadmin atau sesama admin_branch
        if ($authRole === 'admin_branch' && in_array($role, ['superadmin', 'admin_branch'])) {
            abort(403, 'Anda tidak dapat mengubah permission untuk role ini.');
        }

        $permissions = $request->validated()['permissions'];
        $tenantId = $request->user()->tenant_id;

        DB::transaction(function () use ($tenantId, $role, $permissions) {
            RolePermission::where('tenant_id', $tenantId)->where('role', $role)->delete();

            $insertData = [];
            foreach ($permissions as $permission) {
                $insertData[] = [
                    'tenant_id' => $tenantId,
                    'role'      => $role,
                    'permission_key' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($insertData)) {
                RolePermission::insert($insertData);
            }
        });

        return response()->json(['message' => 'Role permissions updated successfully']);
    }

    public function userPermissions(Request $request, User $user)
    {
        $this->assertCanManage($request);

        $effective = $this->permissionService->getEffectivePermissions($user);
        $overrides = UserPermissionOverride::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn($item) => [
                'key'   => $item->permission_key,
                'value' => $item->value,
            ]);

        return response()->json([
            'data' => [
                'effective' => $effective,
                'overrides' => $overrides,
            ]
        ]);
    }

    public function updateUserPermissions(UpdateUserPermissionsRequest $request, User $user)
    {
        $this->assertCanManage($request);

        $authRole = $request->user()->role;

        // admin_branch tidak boleh override permission superadmin / sesama admin_branch
        if ($authRole === 'admin_branch' && in_array($user->role, ['superadmin', 'admin_branch'])) {
            abort(403, 'Anda tidak dapat mengubah permission pengguna dengan role ini.');
        }

        $overrides  = $request->validated()['overrides'];
        $tenantId   = $request->user()->tenant_id;

        DB::transaction(function () use ($tenantId, $user, $overrides) {
            // Delete ALL existing overrides for this user first.
            // This is safer than deleting by key subset because it ensures
            // no stale overrides remain when a permission is reset to null (default).
            UserPermissionOverride::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->delete();

            $insertData = [];
            foreach ($overrides as $override) {
                // Only persist explicit grant/revoke values; null means "use role default".
                if (in_array($override['value'], ['grant', 'revoke'])) {
                    $insertData[] = [
                        'tenant_id'      => $tenantId,
                        'user_id'        => $user->id,
                        'permission_key' => $override['key'],
                        'value'          => $override['value'],
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
            }

            if (!empty($insertData)) {
                UserPermissionOverride::insert($insertData);
            }
        });

        return response()->json(['message' => 'User permissions updated successfully']);
    }
}
