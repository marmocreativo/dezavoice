<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\SalesProspect;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ClientMessageController extends Controller
{
    public function index(Request $request, SalesProspect $prospect): View
    {
        $subscriptions = $prospect->subscriptions()
            ->with(['plan', 'organization'])
            ->orderByDesc('subscriptions.created_at')
            ->get();

        $organizationIds = $subscriptions->pluck('organization_id')->unique()->values();

        $filters = [
            'from' => $this->dateOrEmpty($request->query('from')),
            'to' => $this->dateOrEmpty($request->query('to')),
            'plan' => (string) $request->query('plan', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = ClientMessage::query()
            ->whereIn('organization_id', $organizationIds)
            ->with('plan:id,uuid,name')
            ->when($filters['from'] !== '', fn ($q) => $q->where('fecha', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== '', fn ($q) => $q->where('fecha', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->when($filters['plan'] !== '', fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('uuid', $filters['plan'])))
            ->when($filters['q'] !== '', fn ($q) => $q->where('mensaje', 'like', '%'.$filters['q'].'%'));

        $totals = [
            'messages' => (clone $query)->count(),
            'minutes' => (float) (clone $query)->sum('minutos_consumidos'),
        ];

        $messages = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();

        $plans = Plan::whereIn('id', ClientMessage::whereIn('organization_id', $organizationIds)->whereNotNull('plan_id')->select('plan_id'))
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        return view('admin.messages.index', compact('prospect', 'subscriptions', 'messages', 'totals', 'filters', 'plans'));
    }

    private function dateOrEmpty(mixed $value): string
    {
        return is_string($value) && Carbon::hasFormat($value, 'Y-m-d') ? $value : '';
    }
}