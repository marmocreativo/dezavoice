<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Membership;
use App\Models\ReportingLine;
use App\Models\SalesTeam;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MembershipRoleService
{
    public function __construct(
        private readonly MarketManagerService $managers,
        private readonly TerritorySupervisorService $supervisors,
        private readonly TeamSellersService $sellers,
    ) {}

    /**
     * Asigna un rol a un usuario respetando la jerarquía (gerente por mercado, supervisor por
     * territorio, vendedores por equipo). Con $replace retira sus otras asignaciones del mismo rol.
     *
     * @param  array{market_uuid?: ?string, territory_uuid?: ?string, sales_team_uuid?: ?string}  $scope
     * @return array{changed: bool, message: string}
     */
    public function assign(User $user, string $role, array $scope, bool $replace): array
    {
        return DB::transaction(function () use ($user, $role, $scope, $replace) {
            [$changed, $current, $label] = match ($role) {
                'deza_admin' => $this->assignAdmin($user),
                'manager' => $this->assignManager($user, (string) ($scope['market_uuid'] ?? '')),
                'supervisor' => $this->assignSupervisor($user, (string) ($scope['territory_uuid'] ?? '')),
                'seller' => $this->assignSeller($user, (string) ($scope['sales_team_uuid'] ?? '')),
                default => throw new RuntimeException('Rol no soportado.'),
            };

            $retired = 0;

            if ($replace && $current && $role !== 'deza_admin') {
                $others = Membership::active()
                    ->where('user_id', $user->id)
                    ->where('role', $role)
                    ->whereKeyNot($current->id)
                    ->get();

                foreach ($others as $other) {
                    $this->retire($other);
                    $retired++;
                }
            }

            if (! $changed && $retired === 0) {
                return ['changed' => false, 'message' => 'Ya tenía ese rol con ese alcance; no hubo cambios.'];
            }

            $message = $changed ? "Rol asignado: {$label}." : "Ya era {$label}.";

            if ($retired > 0) {
                $message .= " Se retiró su asignación anterior ({$retired}).";
            }

            return ['changed' => true, 'message' => $message];
        });
    }

    /** Retira (desactiva) una asignación y cierra sus líneas de reporte. */
    public function retire(Membership $membership, ?User $actor = null): void
    {
        if ($membership->status !== 'active') {
            throw new RuntimeException('Esta asignación ya está retirada.');
        }

        if ($membership->role === 'deza_admin') {
            if ($actor && $membership->user_id === $actor->id) {
                throw new RuntimeException('No puedes retirar tu propio rol de administrador.');
            }

            if (Membership::active()->where('role', 'deza_admin')->count() <= 1) {
                throw new RuntimeException('Es el último administrador activo; no se puede retirar.');
            }
        }

        DB::transaction(function () use ($membership) {
            $membership->update(['status' => 'inactive', 'ended_at' => now()]);

            // Hacia arriba y hacia abajo; quienes reportaban a esta asignación quedan sin superior
            // hasta que se asigne al siguiente (los servicios de gerente y supervisor los reenganchan).
            ReportingLine::active()->where('member_membership_id', $membership->id)->update(['ended_at' => now()]);
            ReportingLine::active()->where('manager_membership_id', $membership->id)->update(['ended_at' => now()]);

            // Columna heredada que la API todavía lee.
            Territory::where('assigned_manager_membership_id', $membership->id)->update(['assigned_manager_membership_id' => null]);
        });
    }

    /** @return array{0: bool, 1: ?Membership, 2: string} */
    private function assignAdmin(User $user): array
    {
        $current = Membership::active()->where('user_id', $user->id)->where('role', 'deza_admin')->first();

        if ($current) {
            return [false, $current, 'administrador'];
        }

        $current = Membership::create([
            'user_id' => $user->id,
            'role' => 'deza_admin',
            'status' => 'active',
        ]);

        return [true, $current, 'administrador'];
    }

    /** @return array{0: bool, 1: ?Membership, 2: string} */
    private function assignManager(User $user, string $marketUuid): array
    {
        $market = Market::where('uuid', $marketUuid)->firstOrFail();

        $result = $this->managers->assign($market, [
            'manager_mode' => 'existing',
            'manager_user_uuid' => $user->uuid,
        ]);

        $current = Membership::active()
            ->where('user_id', $user->id)
            ->where('role', 'manager')
            ->where('market_id', $market->id)
            ->first();

        return [$result !== null, $current, "gerente de {$market->name}"];
    }

    /** @return array{0: bool, 1: ?Membership, 2: string} */
    private function assignSupervisor(User $user, string $territoryUuid): array
    {
        $territory = Territory::where('uuid', $territoryUuid)->firstOrFail();

        $result = $this->supervisors->assign($territory, [
            'supervisor_mode' => 'existing',
            'supervisor_user_uuid' => $user->uuid,
        ]);

        $current = Membership::active()
            ->where('user_id', $user->id)
            ->where('role', 'supervisor')
            ->where('territory_id', $territory->id)
            ->first();

        return [$result !== null, $current, "supervisor del territorio {$territory->name}"];
    }

    /** @return array{0: bool, 1: ?Membership, 2: string} */
    private function assignSeller(User $user, string $teamUuid): array
    {
        $team = SalesTeam::where('uuid', $teamUuid)->firstOrFail();

        if (! $team->territory_id) {
            throw new RuntimeException('El equipo no tiene territorio; asígnale uno antes de agregar vendedores.');
        }

        $report = $this->sellers->sync($team, ['existing_sellers' => [$user->uuid]]);

        $current = Membership::active()
            ->where('user_id', $user->id)
            ->where('role', 'seller')
            ->where('sales_team_id', $team->id)
            ->first();

        return [($report['added'] + $report['moved']) > 0, $current, "vendedor del equipo {$team->name}"];
    }
}