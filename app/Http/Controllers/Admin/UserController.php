<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Market;
use App\Models\SalesTeam;
use App\Models\Territory;
use App\Models\User;
use App\Services\UserCredentialsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ROLES = ['deza_admin', 'manager', 'supervisor', 'seller', 'client'];

    public function __construct(private readonly UserCredentialsService $credentials) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => in_array($request->query('role'), self::ROLES, true) ? $request->query('role') : '',
            'market' => (string) $request->query('market', ''),
            'status' => in_array($request->query('status'), ['with_role', 'no_role'], true) ? $request->query('status') : '',
        ];

        $users = User::query()
            ->with(['memberships' => fn ($q) => $q->with(['market:id,code', 'territory:id,name', 'salesTeam:id,name'])])
            ->withCount(['memberships as active_roles_count' => fn ($q) => $q->where('status', 'active')])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['role'] !== '', fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('status', 'active')->where('role', $filters['role'])))
            ->when($filters['market'] !== '', fn ($q) => $q->whereHas('memberships', fn ($m) => $m
                ->where('status', 'active')
                ->whereHas('market', fn ($mk) => $mk->where('uuid', $filters['market']))))
            ->when($filters['status'] === 'with_role', fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('status', 'active')))
            ->when($filters['status'] === 'no_role', fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('status', 'active')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'markets' => Market::orderBy('name')->get(['id', 'uuid', 'code', 'name']),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['memberships' => fn ($q) => $q
            ->with(['market:id,uuid,code,name', 'territory:id,uuid,name', 'salesTeam:id,uuid,name', 'organization:id,uuid,name'])
            ->orderByDesc('id')]);

        return view('admin.users.show', [
            'user' => $user,
            'markets' => Market::orderBy('name')->get(['id', 'uuid', 'code', 'name']),
            'territories' => Territory::with('market:id,code')->orderBy('market_id')->orderBy('name')->get(),
            'teams' => SalesTeam::with('territory.market:id,code')
                ->whereNotNull('territory_id')
                ->where('is_active', true)
                ->orderBy('territory_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $mode = $data['password_mode'];
        $temporaryPassword = null;

        DB::transaction(function () use ($request, $user, $data, $mode, &$temporaryPassword) {
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);

            $dirty = $user->getDirty();
            $before = array_intersect_key($user->getOriginal(), $dirty);
            $after = $dirty;

            if ($mode === 'generate') {
                $temporaryPassword = Str::random(10);
            }

            $newPassword = $mode === 'manual' ? $data['password'] : $temporaryPassword;

            if ($newPassword !== null) {
                $user->password = $newPassword;
                $after['password'] = "cambiada ({$mode})"; // nunca se registra el valor
            }

            // Fijar o generar contraseña obliga a cambiarla en el siguiente acceso.
            $mustChange = $newPassword !== null ? true : $request->boolean('must_change_password');

            if ($mustChange !== (bool) $user->must_change_password) {
                $before['must_change_password'] = (bool) $user->must_change_password;
                $after['must_change_password'] = $mustChange;
            }

            $user->must_change_password = $mustChange;
            $user->save();

            if ($after !== []) {
                AuditLog::record(
                    actor: $request->user()->adminMembership(),
                    action: 'user.updated',
                    subject: $user,
                    before: $before,
                    after: $after,
                );
            }
        });

        $warning = null;

        if ($temporaryPassword !== null) {
            try {
                $this->credentials->send($user, $temporaryPassword);
            } catch (\Throwable $e) {
                report($e);
                $warning = "No se pudo enviar el correo a {$user->email}. Contraseña temporal: {$temporaryPassword} (compártela de forma segura).";
            }
        }

        return redirect()
            ->route('admin.users.show', $user->uuid)
            ->with('status', 'Usuario actualizado.'.($temporaryPassword !== null && ! $warning ? ' Se envió la contraseña temporal por correo.' : ''))
            ->with('warning', $warning);
    }
}