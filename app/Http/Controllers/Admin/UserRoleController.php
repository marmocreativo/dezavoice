<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserRoleController extends Controller
{
    public function __construct(private readonly MembershipRoleService $roles) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['deza_admin', 'manager', 'supervisor', 'seller'])],
            'market_uuid' => ['required_if:role,manager', 'nullable', 'exists:markets,uuid'],
            'territory_uuid' => ['required_if:role,supervisor', 'nullable', 'exists:territories,uuid'],
            'sales_team_uuid' => ['required_if:role,seller', 'nullable', 'exists:sales_teams,uuid'],
        ], [
            'required' => 'Selecciona un valor para :attribute.',
            'required_if' => 'Selecciona :attribute.',
            'in' => ':Attribute no es válido.',
            'exists' => ':Attribute no es válido.',
        ], [
            'role' => 'el rol',
            'market_uuid' => 'el mercado',
            'territory_uuid' => 'el territorio',
            'sales_team_uuid' => 'el equipo',
        ]);

        try {
            $result = $this->roles->assign($user, $data['role'], $data, $request->boolean('replace'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($result['changed']) {
            AuditLog::record(
                actor: $request->user()->adminMembership(),
                action: 'membership.assigned',
                subject: $user,
                before: [],
                after: ['role' => $data['role'], 'detail' => $result['message']],
            );
        }

        return redirect()
            ->to(route('admin.users.show', $user->uuid).'#roles')
            ->with($result['changed'] ? 'status' : 'warning', $result['message']);
    }

    public function retire(Request $request, Membership $membership): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($membership->user_id);

        try {
            $this->roles->retire($membership, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(
            actor: $request->user()->adminMembership(),
            action: 'membership.retired',
            subject: $membership,
            before: ['status' => 'active'],
            after: ['status' => 'inactive'],
        );

        return redirect()
            ->to(route('admin.users.show', $user->uuid).'#roles')
            ->with('status', 'Rol retirado.');
    }
}