@extends('layouts.admin')

@section('title', $prospect->business_name)
@section('heading', 'Prospectos y clientes')

@section('content')
    @php
        $date = fn ($value, $format = 'd/m/Y') => $value ? \Carbon\Carbon::parse($value)->format($format) : '—';
        $money = fn ($cents, $currency) => number_format(((int) $cents) / 100, 2).' '.$currency;
        $owner = $prospect->owner;
        $quotedPlan = $prospect->opportunities->first()?->subscriptionPlan;
        $quotes = $prospect->opportunities->flatMap(fn ($o) => $o->quotes->map(fn ($q) => [$o, $q]));
        $payments = $prospect->opportunities->flatMap(fn ($o) => $o->payments);
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Prospectos y clientes</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-semibold text-slate-900">{{ $prospect->business_name }}</h2>
            <x-admin.status-pill :value="$prospect->status" />
        </div>
        <p class="mt-1 text-sm text-slate-500">
            @if ($prospect->market)
                <a href="{{ route('admin.markets.show', $prospect->market->uuid) }}" class="hover:text-slate-700">{{ $prospect->market->name }}</a>
            @endif
            @if ($prospect->giro) · {{ ucfirst(str_replace('_', ' ', $prospect->giro)) }} @endif
            @if ($prospect->address) · {{ $prospect->address }} @endif
        </p>
        @if ($prospect->lost_reason)
            <p class="mt-1 text-sm text-red-600">Motivo: {{ $prospect->lost_reason }}</p>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Plan y suscripción --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Plan y suscripción</h3>

                @forelse ($prospect->subscriptions as $subscription)
                    @php
                        $opportunity = $prospect->opportunities->firstWhere('id', $subscription->opportunity_id);
                        $lastQuote = $opportunity?->quotes->first();
                        $currency = $subscription->plan?->currency ?? $opportunity?->currency;
                        $organization = $subscription->organization;
                    @endphp

                    <div class="mt-4 rounded-xl border border-slate-200 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-lg font-semibold text-slate-900">{{ $subscription->plan?->name ?? 'Plan eliminado' }}</p>
                                @if ($subscription->plan)
                                    <p class="font-mono text-xs text-slate-500">{{ $subscription->plan->code }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <x-admin.status-pill :value="$subscription->status" type="subscription" />
                                <a href="{{ route('admin.subscriptions.edit', $subscription->uuid) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Editar plan y suscripción</a>
                            </div>
                        </div>

                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-slate-500">Mensualidad cotizada</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ $opportunity?->expected_amount_cents !== null ? $money($opportunity->expected_amount_cents, $currency) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Primer cobro cotizado</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ $lastQuote ? $money($lastQuote->amount_cents, $lastQuote->currency ?? $currency) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Inicio</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $date($subscription->started_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Fin del periodo actual</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $date($subscription->current_period_end) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Cancelada</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $date($subscription->canceled_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Stripe</dt>
                                <dd class="mt-0.5 truncate font-mono text-xs text-slate-600" title="{{ $subscription->stripe_subscription_id }}">{{ $subscription->stripe_subscription_id ?? '—' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4">
                            <x-admin.usage-bar :used="$subscription->minutos_utilizados" :limit="$subscription->minutos_mensuales" />
                            <a href="{{ route('admin.prospects.messages.index', $prospect->uuid) }}" class="mt-2 inline-block text-sm font-medium text-brand-600 hover:text-brand-700">Ver historial de mensajes</a>
                            @if ($organization)
                                <a href="{{ route('web_test.show', $organization->uuid) }}" target="_blank" rel="noopener noreferrer" class="ml-4 mt-2 inline-block text-sm font-medium text-brand-600 hover:text-brand-700">Probar agente (web_test) ↗</a>
                            @endif
                        </div>

                        @if ($subscription->status === 'pending_payment' && $subscription->stripe_checkout_url)
                            <a href="{{ $subscription->stripe_checkout_url }}" target="_blank" rel="noopener noreferrer"
                               class="mt-4 inline-block text-sm font-medium text-brand-600 hover:text-brand-700">Abrir enlace de pago ↗</a>
                        @endif

                        @if ($organization)
                            <div class="mt-5 border-t border-slate-100 pt-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente facturable</p>
                                    <div class="flex items-center gap-4">
                                        <a href="{{ route('admin.organizations.edit', $organization->uuid) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Editar cliente</a>
                                        <a href="{{ route('admin.organizations.agent.edit', $organization->uuid) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Menú del agente</a>
                                    </div>
                                </div>
                                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-slate-500">Nombre</dt><dd class="text-slate-800">{{ $organization->name }}</dd></div>
                                    <div><dt class="text-slate-500">Razón social</dt><dd class="text-slate-800">{{ $organization->legal_name ?? '—' }}</dd></div>
                                    <div><dt class="text-slate-500">ID fiscal</dt><dd class="text-slate-800">{{ $organization->tax_id ?? '—' }}</dd></div>
                                    <div><dt class="text-slate-500">Correo de facturación</dt><dd class="text-slate-800">{{ $organization->billing_email ?? '—' }}</dd></div>
                                    <div><dt class="text-slate-500">Teléfono</dt><dd class="text-slate-800">{{ $organization->phone ?? '—' }}</dd></div>
                                    <div>
                                        <dt class="text-slate-500">Dirección</dt>
                                        <dd class="text-slate-800">
                                            {{ collect([$organization->address_line1, $organization->city, $organization->state, $organization->postal_code, $organization->country])->filter()->join(', ') ?: '—' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-500">
                        Aún no tiene suscripción.
                        @if ($quotedPlan)
                            Último plan cotizado: <span class="font-medium text-slate-800">{{ $quotedPlan->name }}</span>.
                        @endif
                    </p>
                @endforelse
            </div>

            {{-- Comisiones --}}
            @php
                $review = session('commission_review');
                $levelPills = [
                    'ok' => ['Correcto', 'bg-emerald-50 text-emerald-700'],
                    'repaired' => ['Reparado', 'bg-sky-50 text-sky-700'],
                    'info' => ['Info', 'bg-slate-100 text-slate-600'],
                    'warning' => ['Revisar', 'bg-amber-50 text-amber-700'],
                    'error' => ['Problema', 'bg-red-50 text-red-700'],
                ];
                $sectionLabels = ['sold' => 'Venta', 'paid' => 'Pago', 'commissions' => 'Comisiones'];
                $typeLabels = ['activation' => 'Activación', 'recurring' => 'Recurrente'];
                $roleLabels = ['manager' => 'Gerente', 'supervisor' => 'Supervisor', 'seller' => 'Vendedor'];
            @endphp

            <div id="comisiones" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Comisiones generadas</h3>
                        <p class="text-xs text-slate-500">
                            @if ($sales->isNotEmpty())
                                Venta: {{ $money($sales->first()->amount_cents, $sales->first()->currency) }} · estado "{{ $sales->first()->status }}"
                            @else
                                Todavía no hay venta registrada.
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.prospects.commissions.review', $prospect->uuid) }}">
                            @csrf
                            <input type="hidden" name="mode" value="dry">
                            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Solo revisar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.prospects.commissions.review', $prospect->uuid) }}"
                              data-confirm="Se revisará la venta, el pago y las comisiones, y se repararán los registros que falten. ¿Continuar?">
                            @csrf
                            <input type="hidden" name="mode" value="repair">
                            <button type="submit" class="rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Revisar comisiones</button>
                        </form>
                    </div>
                </div>

                {{-- Resultado de la última revisión --}}
                @if ($review)
                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $review['repair'] ? 'Resultado de la revisión (con reparación)' : 'Resultado de la revisión (sin cambios)' }}
                        </p>

                        @foreach ($sectionLabels as $sectionKey => $sectionLabel)
                            @php
                                $items = collect($review['findings'])->where('section', $sectionKey);
                            @endphp
                            @if ($items->isNotEmpty())
                                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $sectionLabel }}</p>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($items as $item)
                                        <li class="flex items-start gap-2 text-sm text-slate-700">
                                            <span class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $levelPills[$item['level']][1] }}">{{ $levelPills[$item['level']][0] }}</span>
                                            <span>{{ $item['message'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Neto por persona --}}
                @if ($commissionSummary->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-3">
                        @foreach ($commissionSummary as $person)
                            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                <p class="font-medium text-slate-800">{{ $person['name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $roleLabels[$person['role'] ?? ''] ?? '' }}</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $money($person['net_cents'], $person['currency']) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Asientos --}}
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="px-4 py-2">Persona</th>
                                <th class="px-4 py-2">Tipo</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2 text-right">Monto</th>
                                <th class="py-2 pl-4">Pago / nota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($ledger as $entry)
                                @php
                                    $isReversal = $entry->reverses_ledger_id !== null;
                                    $isReverted = $entry->reversedBy !== null;
                                    $member = $entry->membership;
                                @endphp
                                <tr @class(['text-slate-400' => $isReverted])>
                                    <td class="py-2 pr-4">{{ $date($entry->created_at, 'd/m/Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        {{ $member?->user?->name ?? '—' }}
                                        <span class="text-xs text-slate-400">{{ $roleLabels[$member->role ?? ''] ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }}
                                        @if ($isReversal) <span class="text-xs text-red-500">· reversa</span> @endif
                                        @if ($isReverted) <span class="text-xs">· revertida</span> @endif
                                    </td>
                                    <td class="px-4 py-2">{{ ucfirst($entry->status) }}</td>
                                    <td @class(['px-4 py-2 text-right font-medium', 'text-red-600' => $entry->amount_cents < 0])>{{ $money($entry->amount_cents, $entry->currency) }}</td>
                                    <td class="py-2 pl-4 text-xs">
                                        @if ($entry->payment) <span class="font-mono">{{ \Illuminate\Support\Str::limit($entry->payment->external_id, 14) }}</span> @endif
                                        @if ($entry->note) <span class="block text-slate-500">{{ $entry->note }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-slate-500">Esta venta no tiene comisiones registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cotizaciones --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Cotizaciones <span class="font-normal text-slate-500">({{ $quotes->count() }})</span></h3>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Plan</th>
                                <th class="px-4 py-2">Versión</th>
                                <th class="px-4 py-2 text-right">Monto</th>
                                <th class="px-4 py-2">Enviada</th>
                                <th class="py-2 pl-4">Vigente hasta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($quotes as [$opportunity, $quote])
                                <tr>
                                    <td class="py-2 pr-4 text-slate-800">{{ $opportunity->subscriptionPlan?->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-slate-600">v{{ $quote->version }}</td>
                                    <td class="px-4 py-2 text-right text-slate-800">{{ $money($quote->amount_cents, $quote->currency) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $date($quote->sent_at) }}</td>
                                    <td class="py-2 pl-4 text-slate-600">{{ $date($quote->valid_until) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin cotizaciones.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagos --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Pagos <span class="font-normal text-slate-500">({{ $payments->count() }})</span></h3>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="px-4 py-2 text-right">Monto</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="py-2 pl-4">Referencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="py-2 pr-4 text-slate-600">{{ $date($payment->confirmed_at ?? $payment->created_at, 'd/m/Y H:i') }}</td>
                                    <td class="px-4 py-2 text-right text-slate-800">{{ $money($payment->amount_cents, $payment->currency) }}</td>
                                    <td class="px-4 py-2"><x-admin.status-pill :value="$payment->status" type="payment" /></td>
                                    <td class="py-2 pl-4 font-mono text-xs text-slate-500" title="{{ $payment->external_id }}">{{ $payment->provider }} · {{ \Illuminate\Support\Str::limit($payment->external_id, 18) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Vendedor --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Vendedor</h3>
                @if ($owner)
                    <div class="mt-4 text-sm">
                        <p class="font-medium text-slate-800">{{ $owner->user?->name }}</p>
                        <p class="text-slate-500">{{ $owner->user?->email }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Código {{ $owner->codigo ?? '—' }}
                            @if ($owner->salesTeam) · {{ $owner->salesTeam->name }} @endif
                            @if ($owner->territory) · {{ $owner->territory->name }} @endif
                        </p>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-500">Sin vendedor asignado.</p>
                @endif
                @if ($prospect->next_action_at)
                    <p class="mt-4 text-xs text-slate-500">Próxima acción: {{ $date($prospect->next_action_at, 'd/m/Y H:i') }}</p>
                @endif
            </div>

            {{-- Contactos --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Contactos</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @forelse ($prospect->contacts as $contact)
                        <li>
                            <p class="font-medium text-slate-800">
                                {{ $contact->name }}
                                @if ($contact->is_primary) <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-600">principal</span> @endif
                            </p>
                            @if ($contact->role) <p class="text-xs text-slate-500">{{ $contact->role }}</p> @endif
                            @if ($contact->email) <p class="text-slate-600">{{ $contact->email }}</p> @endif
                            @if ($contact->phone) <p class="text-slate-600">{{ $contact->phone }}</p> @endif
                        </li>
                    @empty
                        <li class="text-slate-500">Sin contactos.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Demos --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Demos</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @forelse ($prospect->demos as $demo)
                        <li>
                            <p class="text-slate-800">{{ $date($demo->scheduled_at, 'd/m/Y H:i') }}
                                @if ($demo->completed_at) <span class="text-xs text-emerald-700">· realizada</span> @else <span class="text-xs text-amber-600">· pendiente</span> @endif
                            </p>
                            @if ($demo->outcome) <p class="text-xs text-slate-500">{{ $demo->outcome }}</p> @endif
                        </li>
                    @empty
                        <li class="text-slate-500">Sin demos.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Historial --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Historial de estados</h3>
                <ol class="mt-4 space-y-4 border-l border-slate-200 pl-4 text-sm">
                    @forelse ($prospect->statusHistory as $entry)
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1.5 size-2.5 rounded-full bg-brand-500"></span>
                            <p class="text-slate-800">
                                @if ($entry->from_status) {{ ucfirst(str_replace('_', ' ', $entry->from_status)) }} → @endif
                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $entry->to_status)) }}</span>
                            </p>
                            <p class="text-xs text-slate-400">{{ $date($entry->changed_at, 'd/m/Y H:i') }}</p>
                            @if ($entry->note) <p class="mt-0.5 text-xs text-slate-500">{{ $entry->note }}</p> @endif
                        </li>
                    @empty
                        <li class="text-slate-500">Sin movimientos.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
@endsection