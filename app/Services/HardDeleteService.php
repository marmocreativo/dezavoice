<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\SalesProspect;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Borrado definitivo de un usuario o de un prospecto/cliente y de todo lo que depende de él.
 * Descubre las dependencias leyendo las llaves foráneas de la base de datos.
 */
class HardDeleteService
{
    public const LABELS = [
        'users' => 'Usuarios',
        'memberships' => 'Roles (membresías)',
        'reporting_lines' => 'Líneas de reporte',
        'sales_prospects' => 'Prospectos',
        'sales_contacts' => 'Contactos de prospectos',
        'sales_activities' => 'Actividades',
        'demos' => 'Demos',
        'prospect_status_history' => 'Historial de estados',
        'opportunities' => 'Oportunidades',
        'quotes' => 'Cotizaciones',
        'subscriptions' => 'Suscripciones',
        'invoices' => 'Facturas',
        'payments' => 'Pagos de clientes',
        'sales' => 'Ventas',
        'commission_ledger' => 'Asientos de comisión',
        'commission_payouts' => 'Pagos de comisiones',
        'commission_payout_entries' => 'Detalle de pagos de comisiones',
        'bonus_progress' => 'Avance de bonos',
        'notifications' => 'Notificaciones',
        'device_tokens' => 'Dispositivos de notificaciones push',
        'change_requests' => 'Solicitudes de cambio',
        'organizations' => 'Organizaciones cliente',
        'locations' => 'Ubicaciones',
        'client_messages' => 'Mensajes de clientes',
        'retell_calls' => 'Llamadas del agente',
        'client_phone_numbers' => 'Números telefónicos de clientes',
        'agent_profiles' => 'Perfiles del agente (menú)',
        'menu_photos' => 'Fotos del catálogo',
    ];

    /** Columnas que solo atribuyen autoría o jerarquía: la fila de otra persona se conserva y pierde la referencia. */
    private const DETACH_COLUMNS = [
        'territories.assigned_manager_membership_id',
        'sales_teams.manager_membership_id',
    ];

    /** @var array<string, list<array{table: string, column: string, nullable: bool}>>|null */
    private ?array $foreignKeys = null;

    // ---- API pública ----

    /** @return array<string, mixed> */
    public function plan(User $user, bool $includeClients, ?User $actingUser = null): array
    {
        return $this->build(['users' => [$user->id]], $includeClients, $user->id, $actingUser);
    }

    /** @return array<string, mixed> */
    public function planProspect(SalesProspect $prospect, bool $includeClients, ?User $actingUser = null): array
    {
        return $this->build(['sales_prospects' => [$prospect->id]], $includeClients, null, $actingUser);
    }

    /** @return array{counts: array<string, int>, total: int} */
    public function execute(User $user, bool $includeClients, User $actingUser, ?Membership $actor): array
    {
        return $this->run(
            $this->plan($user, $includeClients, $actingUser),
            'user.hard_deleted',
            User::class,
            $user->id,
            ['uuid' => $user->uuid, 'name' => $user->name],
            $includeClients,
            $actor,
        );
    }

    /** @return array{counts: array<string, int>, total: int} */
    public function executeProspect(SalesProspect $prospect, bool $includeClients, User $actingUser, ?Membership $actor): array
    {
        return $this->run(
            $this->planProspect($prospect, $includeClients, $actingUser),
            'prospect.hard_deleted',
            SalesProspect::class,
            $prospect->id,
            ['uuid' => $prospect->uuid, 'business_name' => $prospect->business_name],
            $includeClients,
            $actor,
        );
    }

    // ---- plan ----

    /**
     * @param  array<string, list<int>>  $roots
     * @return array<string, mixed>
     */
    private function build(array $roots, bool $includeClients, ?int $rootUserId, ?User $actingUser): array
    {
        $delete = $roots;
        $detach = [];

        $queue = [];
        foreach ($roots as $table => $ids) {
            $queue[] = [$table, $ids];
        }

        $this->grow($delete, $detach, $queue);

        if ($includeClients) {
            $this->includeClients($delete, $detach, $rootUserId);
        }

        $memberIds = $delete['memberships'] ?? [];
        $userIds = $delete['users'] ?? [];
        $blockers = [];

        if ($actingUser && in_array($actingUser->id, $userIds, true)) {
            $blockers[] = 'Esta eliminación incluiría tu propia cuenta.';
        }

        $remainingAdmins = DB::table('memberships')
            ->where('role', 'deza_admin')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->when($memberIds !== [], fn ($q) => $q->whereNotIn('id', $memberIds))
            ->count();

        if ($remainingAdmins === 0) {
            $blockers[] = 'No puedes eliminar al último administrador activo.';
        }

        $counts = [];
        foreach ($delete as $table => $ids) {
            $counts[$table] = count($ids);
        }
        arsort($counts);

        $detachCounts = [];
        foreach ($detach as $key => $ids) {
            $detachCounts[$key] = count($ids);
        }

        $otherCommissions = ($delete['commission_ledger'] ?? []) === [] ? 0 : DB::table('commission_ledger')
            ->whereIn('id', $delete['commission_ledger'])
            ->when($memberIds !== [], fn ($q) => $q->whereNotIn('membership_id', $memberIds))
            ->count();

        $otherUserIds = array_values(array_diff($userIds, $rootUserId ? [$rootUserId] : []));

        return [
            'delete' => $delete,
            'detach' => $detach,
            'counts' => $counts,
            'detach_counts' => $detachCounts,
            'other_commissions' => $otherCommissions,
            'paid_entries' => count($delete['commission_payout_entries'] ?? []),
            'organizations' => isset($delete['organizations'])
                ? DB::table('organizations')->whereIn('id', $delete['organizations'])->pluck('name')->all()
                : [],
            'extra_users' => $otherUserIds === []
                ? []
                : DB::table('users')->whereIn('id', $otherUserIds)->get(['name', 'email'])->map(fn ($u) => (array) $u)->all(),
            'active_stripe' => isset($delete['subscriptions'])
                ? DB::table('subscriptions')->whereIn('id', $delete['subscriptions'])->whereNotNull('stripe_subscription_id')->where('status', '!=', 'cancelled')->count()
                : 0,
            'files' => [
                'public' => $userIds === [] ? [] : DB::table('users')->whereIn('id', $userIds)->whereNotNull('avatar_path')->pluck('avatar_path')->all(),
                'local' => isset($delete['menu_photos'])
                    ? DB::table('menu_photos')->whereIn('id', $delete['menu_photos'])->pluck('path')->all()
                    : [],
            ],
            'blockers' => $blockers,
        ];
    }

    // ---- ejecución ----

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $before
     * @return array{counts: array<string, int>, total: int}
     */
    private function run(array $plan, string $action, string $subjectType, int $subjectId, array $before, bool $includeClients, ?Membership $actor): array
    {
        if ($plan['blockers'] !== []) {
            throw new RuntimeException(implode(' ', $plan['blockers']));
        }

        $delete = $plan['delete'];

        DB::transaction(function () use ($plan, $delete, $action, $subjectType, $subjectId, $before, $includeClients, $actor) {
            // 1) Lo que solo atribuye autoría o jerarquía conserva su fila y pierde la referencia.
            foreach ($plan['detach'] as $key => $ids) {
                [$table, $column] = explode('.', $key, 2);

                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table($table)->whereIn('id', $chunk)->update([$column => null]);
                }
            }

            // 2) Referencias opcionales entre filas que se van a borrar: se anulan para poder ordenar el borrado.
            foreach ($this->foreignKeys() as $parent => $fks) {
                if (! isset($delete[$parent])) {
                    continue;
                }

                foreach ($fks as $fk) {
                    if (! $fk['nullable'] || ! isset($delete[$fk['table']])) {
                        continue;
                    }

                    foreach (array_chunk($delete[$fk['table']], 500) as $childChunk) {
                        foreach (array_chunk($delete[$parent], 500) as $parentChunk) {
                            DB::table($fk['table'])
                                ->whereIn('id', $childChunk)
                                ->whereIn($fk['column'], $parentChunk)
                                ->update([$fk['column'] => null]);
                        }
                    }
                }
            }

            // Los correos se leen antes de borrar a los usuarios.
            $userIds = $delete['users'] ?? [];
            $emails = $userIds === [] ? [] : DB::table('users')->whereIn('id', $userIds)->pluck('email')->all();

            // 3) Borrado: primero los hijos, luego los padres.
            foreach ($this->deletionOrder(array_keys($delete)) as $table) {
                foreach (array_chunk($delete[$table], 1000) as $chunk) {
                    DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }

            // 4) Lo que no depende de una llave foránea.
            if ($userIds !== []) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();

                if (Schema::hasTable(config('session.table', 'sessions'))) {
                    DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
                }

                if ($emails !== [] && Schema::hasTable('password_reset_tokens')) {
                    DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
                }
            }

            // 5) Constancia de que se borró, sin datos personales.
            AuditLog::create([
                'actor_membership_id' => $actor?->id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'before' => $before,
                'after' => ['include_clients' => $includeClients, 'counts' => $plan['counts']],
            ]);
        });

        Storage::disk('public')->delete($plan['files']['public']);
        Storage::disk('local')->delete($plan['files']['local']);

        return ['counts' => $plan['counts'], 'total' => array_sum($plan['counts'])];
    }

    // ---- cálculo de dependencias ----

    /**
     * Amplía las filas a borrar con todo lo que depende de ellas.
     *
     * @param  array<string, list<int>>  $delete
     * @param  array<string, list<int>>  $detach
     * @param  list<array{0: string, 1: list<int>}>  $queue
     */
    private function grow(array &$delete, array &$detach, array $queue): void
    {
        while ($queue !== []) {
            [$parent, $parentIds] = array_shift($queue);

            foreach ($this->foreignKeys()[$parent] ?? [] as $fk) {
                $childIds = $this->idsReferencing($fk['table'], $fk['column'], $parentIds);

                if ($childIds === []) {
                    continue;
                }

                if ($this->isDetach($fk['table'], $fk['column'])) {
                    $key = $fk['table'].'.'.$fk['column'];
                    $detach[$key] = array_values(array_unique(array_merge($detach[$key] ?? [], $childIds)));

                    continue;
                }

                $known = $delete[$fk['table']] ?? [];
                $new = array_values(array_diff($childIds, $known));

                if ($new === []) {
                    continue;
                }

                $delete[$fk['table']] = array_merge($known, $new);
                $queue[] = [$fk['table'], $new];
            }
        }

        // Lo que se desvincula no puede estar también en la lista de borrado.
        foreach ($detach as $key => $ids) {
            [$table] = explode('.', $key, 2);
            $detach[$key] = array_values(array_diff($ids, $delete[$table] ?? []));

            if ($detach[$key] === []) {
                unset($detach[$key]);
            }
        }
    }

    /** Suma las organizaciones cliente ligadas y a los usuarios que se quedarían sin ningún rol. */
    private function includeClients(array &$delete, array &$detach, ?int $rootUserId): void
    {
        for ($pass = 0; $pass < 6; $pass++) {
            $orgIds = array_unique(array_merge(
                $this->pluckWhereIn('memberships', 'organization_id', $delete['memberships'] ?? []),
                $this->pluckWhereIn('subscriptions', 'organization_id', $delete['subscriptions'] ?? []),
            ));

            $newOrgs = array_values(array_diff($orgIds, $delete['organizations'] ?? []));
            $newUsers = $this->orphanedUsers($delete, $rootUserId);

            if ($newOrgs === [] && $newUsers === []) {
                return;
            }

            if ($newOrgs !== []) {
                $delete['organizations'] = array_merge($delete['organizations'] ?? [], $newOrgs);
                $this->grow($delete, $detach, [['organizations', $newOrgs]]);
            }

            if ($newUsers !== []) {
                $delete['users'] = array_merge($delete['users'] ?? [], $newUsers);
                $this->grow($delete, $detach, [['users', $newUsers]]);
            }
        }
    }

    /** @return list<int> usuarios cuyos roles están todos en el borrado (y que aún no se borran). */
    private function orphanedUsers(array $delete, ?int $rootUserId): array
    {
        $memberIds = $delete['memberships'] ?? [];

        if ($memberIds === []) {
            return [];
        }

        $candidates = array_diff($this->pluckWhereIn('memberships', 'user_id', $memberIds), $delete['users'] ?? []);
        $orphans = [];

        foreach (array_unique($candidates) as $userId) {
            $total = DB::table('memberships')->where('user_id', $userId)->count();
            $inside = DB::table('memberships')->where('user_id', $userId)->whereIn('id', $memberIds)->count();

            if ($total === $inside && $userId !== $rootUserId) {
                $orphans[] = (int) $userId;
            }
        }

        return $orphans;
    }

    private function isDetach(string $table, string $column): bool
    {
        return in_array("{$table}.{$column}", self::DETACH_COLUMNS, true)
            || (bool) preg_match('/(_by_membership_id|_by_user_id|actor_membership_id)$/', $column);
    }

    /** @return list<int> */
    private function idsReferencing(string $table, string $column, array $values): array
    {
        $ids = [];

        foreach (array_chunk($values, 1000) as $chunk) {
            $ids = array_merge($ids, DB::table($table)->whereIn($column, $chunk)->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return list<int> */
    private function pluckWhereIn(string $table, string $column, array $ids): array
    {
        $values = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            $values = array_merge($values, DB::table($table)->whereIn('id', $chunk)->whereNotNull($column)->pluck($column)->all());
        }

        return array_values(array_unique(array_map('intval', $values)));
    }

    // ---- metadatos de la base de datos ----

    /**
     * Llaves foráneas indexadas por tabla padre.
     *
     * @return array<string, list<array{table: string, column: string, nullable: bool}>>
     */
    private function foreignKeys(): array
    {
        if ($this->foreignKeys !== null) {
            return $this->foreignKeys;
        }

        $map = [];

        if (DB::getDriverName() === 'sqlite') {
            $tables = DB::table('sqlite_master')->where('type', 'table')->where('name', 'not like', 'sqlite_%')->pluck('name');

            foreach ($tables as $table) {
                $columns = collect(DB::select('select name, "notnull" as is_required from pragma_table_info(?)', [$table]))->keyBy('name');

                foreach (DB::select('select "table" as parent_table, "from" as child_column from pragma_foreign_key_list(?)', [$table]) as $fk) {
                    $map[$fk->parent_table][] = [
                        'table' => $table,
                        'column' => $fk->child_column,
                        'nullable' => ((int) ($columns[$fk->child_column]->is_required ?? 0)) === 0,
                    ];
                }
            }
        } else {
            $rows = DB::select(<<<'SQL'
                SELECT kcu.TABLE_NAME AS child_table,
                       kcu.COLUMN_NAME AS child_column,
                       kcu.REFERENCED_TABLE_NAME AS parent_table,
                       c.IS_NULLABLE AS is_nullable
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.COLUMNS c
                  ON c.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                 AND c.TABLE_NAME = kcu.TABLE_NAME
                 AND c.COLUMN_NAME = kcu.COLUMN_NAME
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            SQL);

            foreach ($rows as $row) {
                $map[$row->parent_table][] = [
                    'table' => $row->child_table,
                    'column' => $row->child_column,
                    'nullable' => $row->is_nullable === 'YES',
                ];
            }
        }

        return $this->foreignKeys = $map;
    }

    /**
     * Orden de borrado (hijos antes que padres) según las llaves foráneas obligatorias.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function deletionOrder(array $tables): array
    {
        $inSet = array_flip($tables);
        $pending = array_fill_keys($tables, 0);   // hijos que aún deben borrarse antes que esta tabla
        $parentsOf = [];                           // hijo => padres

        foreach ($this->foreignKeys() as $parent => $fks) {
            if (! isset($inSet[$parent])) {
                continue;
            }

            foreach ($fks as $fk) {
                if ($fk['nullable'] || $fk['table'] === $parent || ! isset($inSet[$fk['table']])) {
                    continue;
                }

                $pending[$parent]++;
                $parentsOf[$fk['table']][] = $parent;
            }
        }

        $queue = array_keys(array_filter($pending, fn (int $count) => $count === 0));
        $order = [];

        while ($queue !== []) {
            $table = array_shift($queue);
            $order[] = $table;

            foreach ($parentsOf[$table] ?? [] as $parent) {
                if (--$pending[$parent] === 0) {
                    $queue[] = $parent;
                }
            }
        }

        return array_values(array_merge($order, array_diff($tables, $order)));
    }
}