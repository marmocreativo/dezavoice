<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\Market;
use App\Models\Membership;
use App\Services\CommissionPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

class CommissionPayoutController extends Controller
{
    public const METHODS = ['Transferencia bancaria', 'Yape', 'Plin', 'Efectivo', 'Otro'];

    public function __construct(private readonly CommissionPayoutService $payouts) {}

    /** Comisiones por pagar. */
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'market' => (string) $request->query('market', ''),
            'role' => in_array($request->query('role'), ['seller', 'supervisor', 'manager'], true) ? $request->query('role') : '',
        ];

        $groups = $this->payouts->overview($filters);

        $totals = $groups->groupBy('currency')->map(fn ($rows) => [
            'payable' => (int) $rows->sum('payable_cents'),
            'waiting' => (int) $rows->sum('waiting_cents'),
        ]);

        return view('admin.commissions.index', [
            'groups' => $groups,
            'totals' => $totals,
            'filters' => $filters,
            'markets' => Market::orderBy('name')->get(['id', 'uuid', 'name']),
            'methods' => self::METHODS,
        ]);
    }

    /** Pagos registrados. */
    public function history(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), ['paid', 'voided'], true) ? $request->query('status') : '',
        ];

        $payouts = CommissionPayout::query()
            ->with(['membership.user:id,name,email', 'membership.market:id,code'])
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->whereHas('membership.user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['status'] === 'paid', fn ($q) => $q->whereNull('voided_at'))
            ->when($filters['status'] === 'voided', fn ($q) => $q->whereNotNull('voided_at'))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.commissions.payouts', compact('payouts', 'filters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'membership_uuid' => ['required', 'exists:memberships,uuid'],
            'currency' => ['required', 'string', 'size:3'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*' => ['string', 'exists:commission_ledger,uuid'],
            'paid_at' => ['required', 'date', 'before_or_equal:tomorrow'],
            'method' => ['nullable', 'string', 'in:'.implode(',', self::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'entries.required' => 'Selecciona al menos una comisión.',
            'entries.min' => 'Selecciona al menos una comisión.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'required' => 'El campo :attribute es obligatorio.',
        ], [
            'paid_at' => 'fecha de pago',
            'method' => 'método',
            'reference' => 'referencia',
            'note' => 'nota',
        ]);

        $beneficiary = Membership::withTrashed()->where('uuid', $data['membership_uuid'])->firstOrFail();

        try {
            $payout = $this->payouts->markPaid(
                $beneficiary,
                strtoupper($data['currency']),
                $data['entries'],
                Carbon::parse($data['paid_at']),
                $data['method'] ?? null,
                $data['reference'] ?? null,
                $data['note'] ?? null,
                $request->user()->adminMembership(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return back()->with('error', 'Otra persona acaba de pagar alguna de esas comisiones. Recarga la pantalla.');
        }

        return redirect()
            ->route('admin.commissions.index')
            ->with('status', sprintf('Pago registrado: %s %s a %s.', $payout->currency, number_format($payout->total_cents / 100, 2), $beneficiary->user?->name ?? 'la persona'));
    }

    public function void(Request $request, CommissionPayout $payout): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Escribe el motivo de la anulación.']);

        try {
            $this->payouts->void($payout, $data['reason'], $request->user()->adminMembership());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.commissions.payouts')
            ->with('status', 'Pago anulado. Sus comisiones volvieron a la lista de pendientes.');
    }
}