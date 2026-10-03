<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesProspect;
use App\Services\CommissionAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommissionReviewController extends Controller
{
    public function __construct(private readonly CommissionAuditService $audit) {}

    public function store(Request $request, SalesProspect $prospect): RedirectResponse
    {
        // Sin "mode=repair" explícito, la revisión no cambia nada.
        $repair = $request->input('mode') === 'repair';

        try {
            $report = $this->audit->review($prospect, $repair, $request->user()->adminMembership(), $request->input('rules') === 'current');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'La revisión falló y no se aplicó ningún cambio: '.$e->getMessage());
        }

        $summary = $repair
            ? "Revisión terminada: {$report['repairs']} corrección(es) aplicada(s), {$report['warnings']} aviso(s)."
            : "Revisión sin cambios: {$report['errors']} problema(s) reparable(s), {$report['warnings']} aviso(s).";

        return redirect()
            ->to(route('admin.prospects.show', $prospect->uuid).'#comisiones')
            ->with('status', $summary)
            ->with('commission_review', $report);
    }
}