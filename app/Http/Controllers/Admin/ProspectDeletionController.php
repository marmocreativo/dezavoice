<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesProspect;
use App\Services\HardDeleteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ProspectDeletionController extends Controller
{
    public function __construct(private readonly HardDeleteService $deletion) {}

    /** Vista previa: calcula qué se borraría, sin cambiar nada. */
    public function confirm(Request $request, SalesProspect $prospect): View
    {
        $hasClients = DB::table('subscriptions')
            ->join('opportunities', 'opportunities.id', '=', 'subscriptions.opportunity_id')
            ->where('opportunities.sales_prospect_id', $prospect->id)
            ->exists();

        // Si ya es cliente, lo normal es borrar también su cuenta.
        $includeClients = $request->has('clients') ? $request->boolean('clients') : $hasClients;

        $plan = $this->deletion->planProspect($prospect, $includeClients, $request->user());

        return view('admin.prospects.delete', compact('prospect', 'plan', 'includeClients', 'hasClients'));
    }

    public function destroy(Request $request, SalesProspect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'confirm_name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'confirm_name.required' => 'Escribe el nombre del negocio para confirmar.',
            'password.required' => 'Escribe tu contraseña para confirmar.',
        ]);

        if (Str::lower(trim($data['confirm_name'])) !== Str::lower(trim($prospect->business_name))) {
            return back()->withErrors(['confirm_name' => 'El nombre no coincide con el del negocio.'])->withInput($request->only('clients'));
        }

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Tu contraseña no es correcta.'])->withInput($request->only('clients'));
        }

        $includeClients = $request->boolean('clients');
        $plan = $this->deletion->planProspect($prospect, $includeClients, $request->user());

        if ($plan['active_stripe'] > 0 && ! $request->boolean('stripe_ack')) {
            return back()
                ->withErrors(['stripe_ack' => 'Confirma que ya cancelaste en Stripe las suscripciones activas.'])
                ->withInput($request->only('clients'));
        }

        try {
            $result = $this->deletion->executeProspect($prospect, $includeClients, $request->user(), $request->user()->adminMembership());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo eliminar y no se aplicó ningún cambio: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.prospects.index')
            ->with('status', "«{$prospect->business_name}» eliminado definitivamente ({$result['total']} registros).");
    }
}