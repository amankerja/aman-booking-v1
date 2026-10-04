<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Services\LimitEnforcer;
use App\Domain\Tenant\Models\BusinessMember;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    /**
     * Display a listing of members for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = TenantContext::getTenant();
        abort_unless($tenant instanceof \App\Domain\Tenant\Models\Tenant, 404, 'Tenant tidak ditemukan.');

        $members = BusinessMember::with('user')
            ->where('tenant_id', $tenant->id)
            ->get();

        return response()->json([
            'members' => $members,
        ]);
    }

    /**
     * Store a newly created member in storage, strictly enforcing plan limits.
     */
    public function store(Request $request, LimitEnforcer $limitEnforcer): JsonResponse
    {
        $tenant = TenantContext::getTenant();
        abort_unless($tenant instanceof \App\Domain\Tenant\Models\Tenant, 404, 'Tenant tidak ditemukan.');

        // Enforce subscription plan limit (throws PlanLimitReachedException if limit reached)
        $limitEnforcer->enforce('business_members', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'preset' => ['required', 'string', 'in:MANAGER,FRONT_DESK,STAFF,VIEWER'],
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower((string) $validated['email'])],
            [
                'name' => (string) $validated['name'],
                'password' => Hash::make(Str::random(16)),
                'is_super_admin' => false,
            ]
        );

        // Check if already a member of this tenant
        $exists = BusinessMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_if($exists, 422, 'Pengguna sudah menjadi anggota tim bisnis ini.');

        $member = BusinessMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'preset' => (string) $validated['preset'],
        ]);

        // Assign Spatie role for this tenant team
        setPermissionsTeamId($tenant->id);
        $roleName = match ((string) $validated['preset']) {
            'MANAGER' => 'Manager',
            'FRONT_DESK' => 'Front Desk',
            'STAFF' => 'Staff',
            'VIEWER' => 'Viewer',
            default => 'Viewer',
        };
        $user->assignRole($roleName);

        return response()->json([
            'message' => 'Anggota tim berhasil ditambahkan.',
            'member' => $member->load('user'),
        ], 201);
    }
}
