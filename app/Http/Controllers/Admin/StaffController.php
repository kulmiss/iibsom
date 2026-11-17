<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Staff accounts (Super Admin only). */
class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'staff' => User::whereIn('role', array_keys(config()->array('admin.roles')))->orderBy('name')->get()->map(fn (User $u) => $this->row($u)),
            'roles' => collect(config()->array('admin.roles'))->map(fn ($r, $key) => ['value' => $key, 'label' => $r['label'], 'permissions' => $r['permissions'] === ['*'] ? ['Everything'] : array_map(fn ($p) => config("admin.permissions.{$p}"), $r['permissions'])])->values(),
        ]);
    }

    /** New staff member. A one-time password is shown once; they set up 2FA at first sign-in. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(array_keys(config()->array('admin.roles')))],
        ]);
        $email = Str::lower($data['email']);
        $user = User::firstOrNew(['email' => $email]);
        abort_if($user->exists && $user->isStaff(), 422, 'This person is already staff.');

        $password = Str::password(16, symbols: false);
        $user->name = $user->name ?: strip_tags($data['name']);
        $user->email = $email;
        $user->forceFill(['role' => $data['role'], 'password' => $password, 'email_verified_at' => $user->email_verified_at ?? now(), 'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        Activity::log('staff.created', "Added {$user->name} as ".config("admin.roles.{$data['role']}.label"), $user);

        return response()->json($this->row($user) + ['temporary_password' => $password], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        $role = $request->validate(['role' => ['required', Rule::in(array_keys(config()->array('admin.roles')))]])['role'];
        $this->guardSelfAndLastSuperAdmin($request, $user, $role);

        $old = $user->role;
        $user->forceFill(['role' => $role])->save();
        Activity::log('staff.role_changed', "{$user->name}: {$old} → {$role}", $user);

        return response()->json($this->row($user));
    }

    /** Lost phone: they set up 2FA again at next sign-in. */
    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        abort_if($user->id === $this->authUser($request)->id, 422, 'You cannot reset your own 2FA here.');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        Activity::log('staff.2fa_reset', "Reset 2FA for {$user->name}", $user);

        return response()->json($this->row($user));
    }

    /** New one-time password (forgotten password). */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        $password = Str::password(16, symbols: false);
        $user->forceFill(['password' => $password])->save();
        Activity::log('staff.password_reset', "New password for {$user->name}", $user);

        return response()->json($this->row($user) + ['temporary_password' => $password]);
    }

    /** No longer staff: back to a normal customer, admin password removed. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        $this->guardSelfAndLastSuperAdmin($request, $user, 'customer');
        $user->forceFill(['role' => User::CUSTOMER, 'password' => null, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
        Activity::log('staff.removed', "Removed {$user->name} from staff", $user);

        return response()->json(['removed' => true]);
    }

    private function guardSelfAndLastSuperAdmin(Request $request, User $user, string $newRole): void
    {
        abort_if($user->id === $this->authUser($request)->id, 422, 'You cannot change your own role.');
        if ($user->isSuperAdmin() && $newRole !== 'super_admin') {
            abort_if(User::where('role', 'super_admin')->count() <= 1, 422, 'There must always be at least one Super Admin.');
        }
    }

    /** @return array<string, mixed> */
    private function row(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'role_label' => config("admin.roles.{$u->role}.label"),
            'two_factor' => $u->hasTwoFactor(), 'last_login_at' => $u->last_login_at?->toIso8601String(),
        ];
    }
}
