<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgentProfileRequest;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\MenuPhoto;
use App\Models\Organization;
use App\Models\SalesProspect;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AgentProfileController extends Controller
{
    /** Ejemplos de cómo escribir productos, servicios y precios (los precios van con palabras). */
    private const EXAMPLES = [
        'Restaurante' => <<<'TXT'
PLATOS
- Un cuarto de pollo a la brasa con papas y ensalada: 22 soles con 90 céntimos.
- Medio pollo a la brasa con papas y ensalada: 39 soles con 90 céntimos.

ACOMPAÑAMIENTOS
- Porción adicional de arroz: 5 soles.
- Cada ají adicional: 1 sol con 50 céntimos.

BEBIDAS
- Inca Kola de 500 mililitros: 5 soles con 50 céntimos.
- Botella de agua: 3 soles con 50 céntimos.
TXT,
        'Consultorio' => <<<'TXT'
SERVICIOS
- Consulta general: 80 soles.
- Limpieza dental: 120 soles.
- Evaluación de ortodoncia: 100 soles.

CÓMO SE ATIENDE
- Solo con cita previa, de lunes a sábado.
- La primera consulta dura unos cuarenta minutos.
- Se puede pagar en efectivo, con tarjeta o por transferencia.
TXT,
        'Tienda o servicios' => <<<'TXT'
PRODUCTOS
- Arreglo floral pequeño: 60 soles.
- Arreglo floral grande: 140 soles.
- Caja de chocolates: 45 soles.

SERVICIOS
- Entrega a domicilio dentro de la ciudad: 10 soles.
- Tarjeta con mensaje personalizado: 8 soles.

CONDICIONES
- Los pedidos de entrega se piden con un día de anticipación.
TXT,
    ];

    public function edit(Organization $organization): View
    {
        return view('admin.agent_profiles.edit', [
            'organization' => $organization,
            'profile' => AgentProfile::forOrganization($organization),
            'variables' => AgentProfile::variablesFor($organization),
            'prospect' => $this->prospectFor($organization),
            'examples' => self::EXAMPLES,
            'requestTypes' => AgentProfile::REQUEST_TYPES,
            'photos' => MenuPhoto::where('sales_prospect_id', $this->prospectFor($organization)?->id)->orderBy('id')->get(),
        ]);
    }

    public function update(AgentProfileRequest $request, Organization $organization): RedirectResponse
    {
        $profile = AgentProfile::forOrganization($organization);
        $profile->fill($request->validated());

        $dirty = $profile->getDirty();
        $before = array_intersect_key($profile->getOriginal(), $dirty);

        $profile->save();

        if ($dirty !== []) {
            AuditLog::record(
                actor: $request->user()->adminMembership(),
                action: 'agent_profile.updated',
                subject: $organization,
                before: $before,
                after: $dirty,
            );
        }

        return redirect()
            ->route('admin.organizations.agent.edit', $organization->uuid)
            ->with('status', 'Información del agente guardada. La próxima llamada ya la usa.');
    }

    private function prospectFor(Organization $organization): ?SalesProspect
    {
        return Subscription::where('organization_id', $organization->id)
            ->with('opportunity.prospect')
            ->orderByDesc('id')
            ->first()
            ?->opportunity
            ?->prospect;
    }
}