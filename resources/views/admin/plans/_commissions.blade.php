@php
    $money = fn ($cents) => number_format($cents / 100, 2).' '.$plan->currency;
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<div id="comisiones" class="mt-10 scroll-mt-24">
    <h3 class="text-xl font-semibold text-slate-900">Comisiones por rol</h3>
    <p class="mt-1 text-sm text-slate-500">
        Lo que cobra cada persona de la cadena (vendedor, su supervisor y su gerente) cuando se vende este plan.
        Al guardar se crea una versión nueva desde la fecha elegida; las ventas anteriores conservan lo que ya se calculó.
    </p>

    @if ($commissions['generic'] > 0)
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Este mercado tiene {{ $commissions['generic'] }} regla(s) de comisión sin plan asignado. El sistema solo aplica reglas ligadas a un plan, así que esas no generan comisiones.
        </p>
    @endif

    <form method="POST" action="{{ route('admin.plans.commissions.update', $plan->uuid) }}" class="mt-6 space-y-5" novalidate>
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="max-w-xs">
                <x-admin.input name="effective_from" label="Vigente desde" type="date" :value="now()->toDateString()"
                               hint="Aplica a las ventas desde esa fecha. No se admiten fechas pasadas." />
            </div>
        </div>

        @foreach ($commissions['roles'] as $role => $info)
            @php
                $rule = $info['rule'];
                $version = $info['version'];
                $enabled = old("roles.{$role}.enabled", $rule ? '1' : '0') === '1';
                $name = fn (string $field) => "roles[{$role}][{$field}]";
                $dotted = fn (string $field) => "roles.{$role}.{$field}";
            @endphp

            <div data-role-card class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-base font-semibold text-slate-900">
                        <input type="hidden" name="{{ $name('enabled') }}" value="0">
                        <input type="checkbox" name="{{ $name('enabled') }}" value="1" data-role-toggle @checked($enabled)
                               class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                        {{ $info['label'] }}
                    </label>
                    <span class="text-xs text-slate-500">
                        @if ($version)
                            Versión vigente: v{{ $version->version }} · desde {{ $version->effective_from->format('d/m/Y') }}
                        @else
                            Sin versión vigente
                        @endif
                    </span>
                </div>

                <fieldset data-role-fields @disabled(! $enabled) class="mt-5 space-y-5 disabled:opacity-50">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-admin.input :name="$name('activation')" :dotted="$dotted('activation')" label="Comisión de activación ({{ $plan->currency }})"
                                       type="number" step="0.01" min="0"
                                       :value="$rule ? number_format($rule->activation_amount_cents / 100, 2, '.', '') : null"
                                       hint="Monto fijo por la primera venta. En 0 se generan asientos en cero." />
                        <x-admin.input :name="$name('reversal_window_days')" :dotted="$dotted('reversal_window_days')" label="Ventana de reversión (días)"
                                       type="number" step="1" min="0" :value="$rule?->reversal_window_days ?? 0"
                                       hint="Hasta cuántos días después del pago se puede revertir. 0 = sin límite." />
                        <x-admin.input :name="$name('recurring_percentage')" :dotted="$dotted('recurring_percentage')" label="Comisión recurrente (% del pago)"
                                       type="number" step="0.01" min="0" max="100" :value="$rule?->recurring_percentage"
                                       hint="Sobre cada pago mensual después del primero. Vacío = sin recurrente." />
                        <x-admin.input :name="$name('duration_months')" :dotted="$dotted('duration_months')" label="Durante cuántos meses"
                                       type="number" step="1" min="1" :value="$rule?->duration_months" />
                    </div>

                    <details class="text-sm">
                        <summary class="cursor-pointer text-slate-600">Otros datos (las metas aún no las usa el sistema)</summary>
                        <div class="mt-4 grid gap-5 sm:grid-cols-3">
                            <x-admin.input :name="$name('waiting_period_days')" :dotted="$dotted('waiting_period_days')" label="Días de espera para poder pagar"
                                           type="number" step="1" min="0" :value="$rule?->waiting_period_days ?? 0" />
                            <x-admin.input :name="$name('individual_goal')" :dotted="$dotted('individual_goal')" label="Meta individual (ventas)"
                                           type="number" step="1" min="0" :value="$rule?->individual_goal" />
                            <x-admin.input :name="$name('team_goal')" :dotted="$dotted('team_goal')" label="Meta de equipo (ventas)"
                                           type="number" step="1" min="0" :value="$rule?->team_goal" />
                        </div>
                    </details>
                </fieldset>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar comisiones</button>
        </div>
    </form>

    {{-- Historial --}}
    <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="px-4 pt-4"><h4 class="text-base font-semibold text-slate-900">Historial de versiones</h4></div>
        <table class="mt-2 min-w-full divide-y divide-slate-200 text-sm">
            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Rol</th>
                    <th class="px-4 py-2">Versión</th>
                    <th class="px-4 py-2">Vigencia</th>
                    <th class="px-4 py-2 text-right">Activación</th>
                    <th class="px-4 py-2">Recurrente</th>
                    <th class="px-4 py-2 text-right">Reversión</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($commissions['history'] as $rule)
                    @php
                        $version = $rule->commissionPlan;
                        $state = match (true) {
                            $version->effective_from->isFuture() => ['Programada', 'bg-sky-50 text-sky-700'],
                            $version->effective_to && $version->effective_to->lt(today()) => ['Cerrada', 'bg-slate-100 text-slate-600'],
                            default => ['Vigente', 'bg-emerald-50 text-emerald-700'],
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-2 text-slate-800">{{ \App\Services\CommissionRuleService::ROLES[$version->role] ?? $version->role }}</td>
                        <td class="px-4 py-2">
                            <span class="text-slate-700">v{{ $version->version }}</span>
                            <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $state[1] }}">{{ $state[0] }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $version->effective_from->format('d/m/Y') }} → {{ $version->effective_to?->format('d/m/Y') ?? 'sin fin' }}
                        </td>
                        <td class="px-4 py-2 text-right text-slate-800">{{ $money($rule->activation_amount_cents) }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $rule->recurring_percentage ? $number($rule->recurring_percentage).' % × '.$rule->duration_months.' meses' : '—' }}
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">{{ $rule->reversal_window_days ? $rule->reversal_window_days.' días' : 'Sin límite' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Este plan todavía no tiene comisiones definidas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
    <script>
        document.querySelectorAll('[data-role-card]').forEach((card) => {
            const toggle = card.querySelector('[data-role-toggle]');
            const fields = card.querySelector('[data-role-fields]');
            toggle.addEventListener('change', () => { fields.disabled = !toggle.checked; });
        });
    </script>
@endpush