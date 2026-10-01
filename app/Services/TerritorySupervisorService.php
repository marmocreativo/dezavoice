<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\ReportingLine;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TerritorySupervisorService
{
    /**
     * Asigna o cambia al supervisor de un territorio. Debe llamarse dentro de una transacción.
     *
     * @return array{user: User, password: ?string}|null  null si no hubo cambios
     */
    public function assign(Territory $territory, array $data): ?array
    {
        $mode = $data['supervisor_mode'] ?? 'keep';

        if ($mode === 'keep') {
            return null;
        }

        $password = null;

        if ($mode === 'new') {
            $password = Str::random(10);

            $user = User::create([
                'name' => $data['supervisor_name'],
                'email' => $data['supervisor_email'],
                'password' => $password,
                'must_change_password' => true,
            ]);
        } else {
            $user = User::where('uuid', $data['supervisor_user_uuid'])->firstOrFail();
        }

        $current = $this->activeSupervisors($territory);

        if ($current->contains('user_id', $user->id)) {
            return null; // ya es el supervisor de este territorio
        }

        $new = Membership::create([
            'user_id' => $user->id,
            'role' => 'supervisor',
            'market_id' => $territory->market_id,
            'territory_id' => $territory->id,
            'status' => 'active',
        ]);

        $manager = Membership::active()
            ->where('role', 'manager')
            ->where('market_id', $territory->market_id)
            ->first();

        if ($manager) {
            ReportingLine::create([
                'member_membership_id' => $new->id,
                'manager_membership_id' => $manager->id,
            ]);
        }

        $this->retire($current, $new);

        // Vendedores del territorio sin superior (p. ej. dados de alta antes de que hubiera supervisor).
        Membership::active()
            ->where('role', 'seller')
            ->where('territory_id', $territory->id)
            ->whereDoesntHave('reportsTo')
            ->get()
            ->each(fn (Membership $seller) => ReportingLine::create([
                'member_membership_id' => $seller->id,
                'manager_membership_id' => $new->id,
            ]));

        return ['user' => $user, 'password' => $password];
    }

    /** Retira a los supervisores del territorio sin sucesor (al eliminarlo). */
    public function retireAll(Territory $territory): void
    {
        $this->retire($this->activeSupervisors($territory), null);
    }

    private function activeSupervisors(Territory $territory): Collection
    {
        return Membership::active()
            ->where('role', 'supervisor')
            ->where('territory_id', $territory->id)
            ->get();
    }

    private function retire(Collection $olds, ?Membership $successor): void
    {
        foreach ($olds as $old) {
            $old->update(['status' => 'inactive', 'ended_at' => now()]);

            // Cierra su línea hacia el gerente.
            ReportingLine::active()
                ->where('member_membership_id', $old->id)
                ->update(['ended_at' => now()]);

            // Sus vendedores pasan al sucesor, conservando el historial.
            ReportingLine::active()
                ->where('manager_membership_id', $old->id)
                ->get()
                ->each(function (ReportingLine $line) use ($successor) {
                    $line->update(['ended_at' => now()]);

                    if ($successor) {
                        ReportingLine::create([
                            'member_membership_id' => $line->member_membership_id,
                            'manager_membership_id' => $successor->id,
                        ]);
                    }
                });
        }
    }
}