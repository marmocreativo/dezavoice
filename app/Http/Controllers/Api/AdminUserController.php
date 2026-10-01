<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddRoleRequest;
use App\Http\Requests\AdminResetPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Membership;
use App\Models\User;
use App\Services\MailTemplateService;
use App\Services\PersonInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly PersonInvitationService $invitationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = $request->query('q');

        $users = User::query()
            ->with(['memberships' => fn ($q) => $q->with(['market:id,uuid,name', 'territory:id,uuid,name', 'salesTeam:id,uuid,name'])])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users->map(fn (User $u) => $this->serializeUser($u))]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user->update($request->validated());

        return response()->json(['data' => $this->serializeUser($user->fresh('memberships'))]);
    }

    public function resendInvitation(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $membership = $user->memberships()->active()->first();

        if (! $membership) {
            return response()->json(['message' => 'Este usuario no tiene ninguna membresía activa.'], 422);
        }

        $temporaryPassword = Str::random(10);
        $user->update(['password' => $temporaryPassword, 'must_change_password' => true]);

        $this->sendCredentialsEmail($user, $membership->role, $temporaryPassword);

        return response()->json(['message' => 'Invitación reenviada.']);
    }

    public function resetPassword(AdminResetPasswordRequest $request, User $user)
    {
        $this->authorizeAdmin($request);

        $data = $request->validated();

        if ($data['mode'] === 'manual') {
            $user->update(['password' => $data['password'], 'must_change_password' => true]);

            return response()->json(['message' => 'Contraseña actualizada.']);
        }

        $temporaryPassword = Str::random(10);
        $user->update(['password' => $temporaryPassword, 'must_change_password' => true]);

        $membership = $user->memberships()->active()->first();
        $this->sendCredentialsEmail($user, $membership?->role ?? 'usuario', $temporaryPassword);

        return response()->json(['message' => 'Contraseña generada y enviada por correo.']);
    }

    public function addRole(AddRoleRequest $request, User $user)
    {
        $this->authorizeAdmin($request);

        $membership = $this->invitationService->addRole($user, $request->validated());

        return response()->json(['data' => $this->serializeUser($user->fresh('memberships'))]);
    }

    public function destroyMembership(Request $request, Membership $membership)
    {
        $this->authorizeAdmin($request);

        $user = $membership->user;

        // Borrado en cascada de todo lo ligado a esta membresía específica.
        \App\Models\ReportingLine::where('member_membership_id', $membership->id)->forceDelete();
        \App\Models\ReportingLine::where('manager_membership_id', $membership->id)->forceDelete();
        \App\Models\CommissionLedger::where('membership_id', $membership->id)->forceDelete();
        \App\Models\BonusProgress::where('membership_id', $membership->id)->forceDelete();
        \App\Models\SalesProspect::where('owner_membership_id', $membership->id)->forceDelete();
        \App\Models\Sale::where('seller_membership_id', $membership->id)->forceDelete();

        $membership->forceDelete();

        return response()->json(['data' => $this->serializeUser($user->fresh('memberships'))]);
    }

    private function sendCredentialsEmail(User $user, string $role, string $temporaryPassword): void
    {
        $loginUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login';
        $roleLabels = ['manager' => 'Gerente', 'supervisor' => 'Supervisor', 'seller' => 'Vendedor'];
        $roleLabel = $roleLabels[$role] ?? $role;

        $html = MailTemplateService::layout(
            title: 'Tus datos de acceso — DEZA Voice',
            bodyHtml: "
                <p style=\"margin:0 0 16px;\">Hola " . MailTemplateService::e($user->name) . ",</p>
                <p style=\"margin:0 0 16px;\">Se han actualizado tus datos de acceso como <strong>" . MailTemplateService::e($roleLabel) . "</strong> en DEZA Voice.</p>
                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 0 20px;background:#F4F6F8;border-radius:10px;\">
                    <tr>
                        <td style=\"padding:16px 20px;\">
                            <p style=\"margin:0 0 4px;color:#6B7A87;font-size:13px;\">Correo</p>
                            <p style=\"margin:0 0 14px;color:#091925;font-size:15px;\">" . MailTemplateService::e($user->email) . "</p>
                            <p style=\"margin:0 0 4px;color:#6B7A87;font-size:13px;\">Contraseña temporal</p>
                            <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\">
                                <tr>
                                    <td style=\"background:#FFFFFF;border:1.5px dashed #2DBB7F;border-radius:6px;padding:10px 16px;\">
                                        <span style=\"font-family:'Courier New',monospace;font-size:18px;font-weight:700;letter-spacing:1px;color:#091925;user-select:all;\">" . MailTemplateService::e($temporaryPassword) . "</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 0 20px;\">
                    <tr>
                        <td style=\"border-radius:8px;background:#FF6A00;\">
                            <a href=\"" . MailTemplateService::e($loginUrl) . "\" style=\"display:inline-block;padding:14px 28px;color:#FFFFFF;font-weight:600;font-size:15px;text-decoration:none;border-radius:8px;\">Iniciar sesión</a>
                        </td>
                    </tr>
                </table>
                <p style=\"margin:0;color:#6B7A87;font-size:13px;\">Se te pedirá que cambies tu contraseña la próxima vez que ingreses.</p>
            ",
        );

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)->subject('Tus datos de acceso — DEZA Voice');
        });
    }

    private function serializeUser(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null,
            'memberships' => $user->memberships->map(fn (Membership $m) => [
                'uuid' => $m->uuid,
                'role' => $m->role,
                'codigo' => $m->codigo,
                'status' => $m->status,
                'market' => $m->market ? ['uuid' => $m->market->uuid, 'name' => $m->market->name] : null,
                'territory' => $m->territory ? ['uuid' => $m->territory->uuid, 'name' => $m->territory->name] : null,
                'sales_team' => $m->salesTeam ? ['uuid' => $m->salesTeam->uuid, 'name' => $m->salesTeam->name] : null,
            ]),
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'deza_admin', 403, 'Solo un administrador puede gestionar usuarios.');
    }
}