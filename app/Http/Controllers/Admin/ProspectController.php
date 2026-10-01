<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Plan;
use App\Models\SalesProspect;
use App\Models\Subscription;
use App\Services\SalesStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProspectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => in_array($request->query('type'), ['prospects', 'clients'], true) ? $request->query('type') : 'all',
            'market' => (string) $request->query('market', ''),
            'status' => in_array($request->query('status'), SalesProspect::STATUSES, true) ? $request->query('status') : '',
            'plan' => (string) $request->query('plan', ''),
            'subscription' => (string) $request->query('subscription', ''),
        ];

        $base = SalesProspect::query()
            ->when($filters['market'] !== '', fn ($q) => $q->whereHas('market', fn ($m) => $m->where('uuid', $filters['market'])));

        $counts = [
            'all' => (clone $base)->count(),
            'clients' => (clone $base)->whereIn('status', SalesStatsService::WON)->count(),
        ];
        $counts['prospects'] = $counts['all'] - $counts['clients'];

        $prospects = (clone $base)
            ->with([
                'market:id,uuid,code,name',
                'owner.user:id,name',
                'subscriptions.plan:id,uuid,code,name',
                'opportunities' => fn ($q) => $q->latest()->with('subscriptionPlan:id,uuid,code,name'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.$filters['q'].'%';

                $query->where(function ($w) use ($like) {
                    $w->where('business_name', 'like', $like)
                        ->orWhereHas('contacts', fn ($c) => $c
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like));
                });
            })
            ->when($filters['type'] === 'clients', fn ($q) => $q->whereIn('status', SalesStatsService::WON))
            ->when($filters['type'] === 'prospects', fn ($q) => $q->whereNotIn('status', SalesStatsService::WON))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($q) => $q->whereHas('opportunities', fn ($o) => $o
                ->whereHas('subscriptionPlan', fn ($p) => $p->where('uuid', $filters['plan']))))
            ->when($filters['subscription'] === 'none', fn ($q) => $q->whereDoesntHave('subscriptions'))
            ->when(! in_array($filters['subscription'], ['', 'none'], true), fn ($q) => $q
                ->whereHas('subscriptions', fn ($s) => $s->where('subscriptions.status', $filters['subscription'])))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.prospects.index', [
            'prospects' => $prospects,
            'filters' => $filters,
            'counts' => $counts,
            'markets' => Market::orderBy('name')->get(['id', 'uuid', 'code', 'name']),
            'plans' => Plan::with('market:id,code')->orderBy('market_id')->orderBy('price_cents')->get(),
            'subscriptionStatuses' => Subscription::query()->distinct()->orderBy('status')->pluck('status'),
        ]);
    }

    public function show(SalesProspect $prospect): View
    {
        $prospect->load([
            'market',
            'owner.user:id,name,email',
            'owner.salesTeam:id,name',
            'owner.territory:id,name',
            'contacts',
            'demos' => fn ($q) => $q->orderByDesc('scheduled_at'),
            'opportunities' => fn ($q) => $q->latest()->with([
                'subscriptionPlan',
                'quotes' => fn ($quotes) => $quotes->orderByDesc('version'),
                'payments' => fn ($payments) => $payments->orderByDesc('confirmed_at'),
            ]),
            'subscriptions' => fn ($q) => $q->orderByDesc('subscriptions.created_at')->with(['plan', 'organization']),
            'statusHistory' => fn ($q) => $q->orderByDesc('changed_at'),
        ]);

        $sales = \App\Models\Sale::whereIn('opportunity_id', $prospect->opportunities->pluck('id'))->get();

        $ledger = \App\Models\CommissionLedger::query()
            ->whereIn('sale_id', $sales->pluck('id'))
            ->with(['membership.user:id,name', 'payment:id,uuid,external_id,confirmed_at', 'reversedBy:id,reverses_ledger_id'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Neto por persona (los asientos de reversa son negativos).
        $commissionSummary = $ledger->groupBy('membership_id')->map(fn ($rows) => [
            'name' => $rows->first()->membership?->user?->name ?? '—',
            'role' => $rows->first()->membership?->role,
            'net_cents' => (int) $rows->sum('amount_cents'),
            'currency' => $rows->first()->currency,
        ])->values();

        return view('admin.prospects.show', compact('prospect', 'sales', 'ledger', 'commissionSummary'));
    }
}