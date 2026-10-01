<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\SalesTeam;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

use App\Services\MailTemplateService;

class PersonInvitationService
{
    /**
     * Da de alta una persona nueva con una membresía, validando que el
     * actor tenga autoridad real sobre el rol y el alcance solicitados.
     */
    public function invite(Membership $actor, array $data): Membership
    {
        $this->assertCanInvite($actor, $data);

        return DB::transaction(function () use ($actor, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $salesTeamId = in_array($data['role'], ['supervisor', 'seller'], true)
                ? $this->salesTeamId($data['sales_team_uuid'])
                : null;

            $territoryId = match (true) {
                $data['role'] === 'manager' => $this->territoryId($data['territory_uuid']),
                $salesTeamId !== null => SalesTeam::where('id', $salesTeamId)->value('territory_id'),
                default => null,
            };

            $membership = Membership::create([
                'user_id' => $user->id,
                'role' => $data['role'],
                'market_id' => $this->resolveMarketId($actor, $data),
                'territory_id' => $territoryId,
                'sales_team_id' => $salesTeamId,
                'status' => 'active',
            ]);

            $this->createReportingLine($membership, $salesTeamId);

            $this->sendWelcomeEmail($user, $data['role'], $data['password']);

            return $membership;
        });
    }

    /**
     * Agrega un rol (nueva Membership) a un usuario que ya existe.
     * Solo deza_admin puede usar esto — no valida autoridad de alcance
     * como invite(), ya que es una acción administrativa directa.
     */
    public function addRole(User $user, array $data): Membership
    {
        return DB::transaction(function () use ($user, $data) {
            $marketId = ! empty($data['market_uuid'])
                ? \App\Models\Market::where('uuid', $data['market_uuid'])->value('id')
                : null;

            $salesTeamId = $this->salesTeamId($data['sales_team_uuid'] ?? null);

            $territoryId = match (true) {
                $data['role'] === 'manager' => $this->territoryId($data['territory_uuid'] ?? null),
                $salesTeamId !== null => SalesTeam::where('id', $salesTeamId)->value('territory_id'),
                default => $this->territoryId($data['territory_uuid'] ?? null),
            };

            if (! $marketId && $salesTeamId) {
                $marketId = SalesTeam::where('id', $salesTeamId)->value('market_id');
            }

            if (! $marketId && $territoryId) {
                $marketId = Territory::where('id', $territoryId)->value('market_id');
            }

            $membership = Membership::create([
                'user_id' => $user->id,
                'role' => $data['role'],
                'market_id' => $marketId,
                'territory_id' => $territoryId,
                'sales_team_id' => $salesTeamId,
                'status' => 'active',
            ]);

            $this->createReportingLine($membership, $salesTeamId);

            return $membership;
        });
    }

    private const ROLE_LABELS = [
        'manager' => 'Gerente',
        'supervisor' => 'Supervisor',
        'seller' => 'Vendedor',
    ];

    public function sendWelcomeEmail(User $user, string $role, string $temporaryPassword): void
    {
        $loginUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login';
        $roleLabel = self::ROLE_LABELS[$role] ?? $role;

        $html = MailTemplateService::layout(
            title: 'Bienvenido a DEZA Voice',
            bodyHtml: "
                <p style=\"margin:0 0 16px;\">Hola " . MailTemplateService::e($user->name) . ",</p>
                <p style=\"margin:0 0 16px;\">Se ha creado tu cuenta como <strong>" . MailTemplateService::e($roleLabel) . "</strong> en DEZA Voice. Estos son tus datos de acceso:</p>
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
                <p style=\"margin:0;color:#6B7A87;font-size:13px;\">Se te pedirá que cambies tu contraseña la primera vez que ingreses.</p>
            ",
        );

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)->subject('Bienvenido a DEZA Voice');
        });
    }

    /**
     * Crea la línea de reporte hacia el superior correspondiente según
     * el equipo asignado: el vendedor reporta al supervisor del equipo,
     * el supervisor reporta al gerente dueño del equipo.
     */
    private function createReportingLine(Membership $membership, ?int $salesTeamId): void
    {
        if (! $salesTeamId) {
            return;
        }

        $team = SalesTeam::find($salesTeamId);

        if (! $team) {
            return;
        }

        $managerMembershipId = match ($membership->role) {
            'seller' => Membership::where('sales_team_id', $salesTeamId)->where('role', 'supervisor')->value('id'),
            'supervisor' => $team->manager_membership_id,
            default => null,
        };

        if (! $managerMembershipId || $managerMembershipId === $membership->id) {
            return;
        }

        \App\Models\ReportingLine::create([
            'member_membership_id' => $membership->id,
            'manager_membership_id' => $managerMembershipId,
        ]);
    }

    private function assertCanInvite(Membership $actor, array $data): void
    {
        $role = $data['role'];

        if ($actor->role === 'deza_admin') {
            return; // el admin puede crear cualquier rol operativo, sin restricción de alcance
        }

        if ($actor->role === 'manager') {
            if (! in_array($role, ['supervisor', 'seller'], true)) {
                throw new RuntimeException('Un gerente solo puede dar de alta supervisores o vendedores.');
            }

            if (! empty($data['sales_team_uuid'])) {
                $team = SalesTeam::where('uuid', $data['sales_team_uuid'])->first();

                if (! $team || $team->manager_membership_id !== $actor->id) {
                    throw new RuntimeException('Solo puedes asignar personas a equipos que administras.');
                }
            }

            return;
        }

        if ($actor->role === 'supervisor') {
            if ($role !== 'seller') {
                throw new RuntimeException('Un supervisor solo puede dar de alta vendedores.');
            }

            if (empty($data['sales_team_uuid']) || $data['sales_team_uuid'] !== $this->ownTeamUuid($actor)) {
                throw new RuntimeException('Solo puedes asignar vendedores a tu propio equipo.');
            }

            return;
        }

        throw new RuntimeException('Tu rol no tiene autoridad para dar de alta personas.');
    }

    private function resolveMarketId(Membership $actor, array $data): ?int
    {
        if ($actor->market_id) {
            return $actor->market_id;
        }

        // El admin no tiene mercado propio; se infiere del territorio o equipo indicado.
        if (! empty($data['territory_uuid'])) {
            return Territory::where('uuid', $data['territory_uuid'])->value('market_id');
        }

        if (! empty($data['sales_team_uuid'])) {
            return SalesTeam::where('uuid', $data['sales_team_uuid'])->value('market_id');
        }

        throw new RuntimeException('No se pudo determinar el mercado para esta membresía.');
    }

    private function territoryId(?string $uuid): ?int
    {
        return $uuid ? Territory::where('uuid', $uuid)->value('id') : null;
    }

    private function salesTeamId(?string $uuid): ?int
    {
        return $uuid ? SalesTeam::where('uuid', $uuid)->value('id') : null;
    }

    private function ownTeamUuid(Membership $actor): ?string
    {
        return SalesTeam::where('id', $actor->sales_team_id)->value('uuid');
    }
}