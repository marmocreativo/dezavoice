<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Membership;
use App\Models\ReportingLine;
use App\Models\SalesTeam;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Str;

class MarketManagerService
{
    /**
     * Asigna o cambia al gerente de un mercado. Debe llamarse dentro de una transacción.
     *
     * @return array{user: User, password: ?string}|null  null si no hubo cambios
     */
    public function assign(Market $market, array $data): ?array
    {
        $mode = $data['manager_mode'] ?? 'keep';

        if ($mode === 'keep') {
            return null;
        }

        $password = null;

        if ($mode === 'new') {
            $password = Str::random(10);

            $user = User::create([
                'name' => $data['manager_name'],
                'email' => $data['manager_email'],
                'password' => $password,
                'must_change_password' => true,
            ]);
        } else {
            $user = User::where('uuid', $data['manager_user_uuid'])->firstOrFail();
        }

        $current = Membership::active()
            ->where('role', 'manager')
            ->where('market_id', $market->id)
            ->get();

        if ($current->contains('user_id', $user->id)) {
            return null; // ya es el gerente de este mercado
        }

        $new = Membership::create([
            'user_id' => $user->id,
            'role' => 'manager',
            'market_id' => $market->id,
            'status' => 'active',
        ]);

        foreach ($current as $old) {
            $old->update(['status' => 'inactive', 'ended_at' => now()]);

            // Su equipo directo pasa al nuevo gerente, conservando el historial.
            ReportingLine::active()
                ->where('manager_membership_id', $old->id)
                ->get()
                ->each(function (ReportingLine $line) use ($new) {
                    $line->update(['ended_at' => now()]);

                    ReportingLine::create([
                        'member_membership_id' => $line->member_membership_id,
                        'manager_membership_id' => $new->id,
                    ]);
                });

            // Columnas heredadas que la API y la PWA todavía leen.
            SalesTeam::where('manager_membership_id', $old->id)
                ->update(['manager_membership_id' => $new->id]);

            Territory::where('assigned_manager_membership_id', $old->id)
                ->update(['assigned_manager_membership_id' => $new->id]);
        }

        // Supervisores del mercado que quedaron sin superior (p. ej. dados de alta antes de que hubiera gerente).
        Membership::active()
            ->where('role', 'supervisor')
            ->where('market_id', $market->id)
            ->whereDoesntHave('reportsTo')
            ->get()
            ->each(fn (Membership $supervisor) => ReportingLine::create([
                'member_membership_id' => $supervisor->id,
                'manager_membership_id' => $new->id,
            ]));

        return ['user' => $user, 'password' => $password];
    }
}