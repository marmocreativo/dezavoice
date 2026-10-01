<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\ReportingLine;
use App\Models\SalesTeam;
use App\Models\User;
use Illuminate\Support\Str;

class TeamSellersService
{
    /**
     * Aplica los cambios de vendedores de un equipo: retira, agrega/mueve existentes
     * y da de alta nuevos. Debe llamarse dentro de una transacción.
     *
     * @return array{added: int, moved: int, removed: int, created: list<array{user: User, password: string}>}
     */
    public function sync(SalesTeam $team, array $data): array
    {
        $supervisor = $this->supervisorFor($team);
        $report = ['added' => 0, 'moved' => 0, 'removed' => 0, 'created' => []];

        // 1) Retirar
        foreach ($data['remove_sellers'] ?? [] as $membershipUuid) {
            $membership = $this->activeSellers()
                ->where('sales_team_id', $team->id)
                ->where('uuid', $membershipUuid)
                ->first();

            if ($membership) {
                $this->retire($membership);
                $report['removed']++;
            }
        }

        // 2) Personas que ya existen: se agregan o se mueven desde otro equipo
        foreach ($data['existing_sellers'] ?? [] as $userUuid) {
            $user = User::where('uuid', $userUuid)->first();

            if (! $user) {
                continue;
            }

            $membership = $this->activeSellers()->where('user_id', $user->id)->first();

            if ($membership) {
                if ((int) $membership->sales_team_id === (int) $team->id) {
                    continue;
                }

                $this->closeUpLine($membership);

                $membership->update([
                    'market_id' => $team->market_id,
                    'territory_id' => $team->territory_id,
                    'sales_team_id' => $team->id,
                ]);

                $this->link($membership, $supervisor);
                $report['moved']++;

                continue;
            }

            $this->link($this->createMembership($user, $team), $supervisor);
            $report['added']++;
        }

        // 3) Personas nuevas
        foreach ($data['new_sellers'] ?? [] as $row) {
            $password = Str::random(10);

            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => $password,
                'must_change_password' => true,
            ]);

            $this->link($this->createMembership($user, $team), $supervisor);
            $report['created'][] = ['user' => $user, 'password' => $password];
        }

        return $report;
    }

    private function activeSellers()
    {
        return Membership::active()->where('role', 'seller');
    }

    private function createMembership(User $user, SalesTeam $team): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'role' => 'seller',
            'market_id' => $team->market_id,
            'territory_id' => $team->territory_id,
            'sales_team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    private function retire(Membership $membership): void
    {
        $membership->update(['status' => 'inactive', 'ended_at' => now()]);
        $this->closeUpLine($membership);
    }

    private function closeUpLine(Membership $membership): void
    {
        ReportingLine::active()
            ->where('member_membership_id', $membership->id)
            ->update(['ended_at' => now()]);
    }

    private function link(Membership $seller, ?Membership $supervisor): void
    {
        if (! $supervisor) {
            return;
        }

        ReportingLine::create([
            'member_membership_id' => $seller->id,
            'manager_membership_id' => $supervisor->id,
        ]);
    }

    /** Supervisor del territorio (si hay datos del modelo anterior, prefiere el del propio equipo). */
    private function supervisorFor(SalesTeam $team): ?Membership
    {
        $base = fn () => Membership::active()
            ->where('role', 'supervisor')
            ->where('territory_id', $team->territory_id);

        return $base()->where('sales_team_id', $team->id)->first() ?? $base()->first();
    }
}