<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgentProfileRequest;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SalesProspect;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AgentProfileController extends Controller
{
    private const MENU_EXAMPLE = <<<'TXT'
PLATOS
- Un cuarto de pollo a la brasa con papas y ensalada: 22 soles con 90 céntimos.
- Medio pollo a la brasa con papas y ensalada: 39 soles con 90 céntimos.
- Un pollo entero a la brasa con papas y ensalada: 69 soles con 90 céntimos.

ACOMPAÑAMIENTOS
- Porción adicional de arroz: 5 soles.
- Porción adicional de papas: 6 soles.
- Cada ají adicional: 1 sol con 50 céntimos.
- Ensalada adicional: 5 soles.

BEBIDAS
- Inca Kola de 500 mililitros: 5 soles con 50 céntimos.
- Coca-Cola de 500 mililitros: 5 soles con 50 céntimos.
- Botella de agua: 3 soles con 50 céntimos.
TXT;

    public function edit(Organization $organization): View
    {
        return view('admin.agent_profiles.edit', [
            'organization' => $organization,
            'profile' => AgentProfile::firstOrNew(['organization_id' => $organization->id]),
            'variables' => AgentProfile::variablesFor($organization),
            'prospect' => $this->prospectFor($organization),
            'example' => self::MENU_EXAMPLE,
        ]);
    }

    public function update(AgentProfileRequest $request, Organization $organization): RedirectResponse
    {
        $profile = AgentProfile::firstOrNew(['organization_id' => $organization->id]);
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
            ->with('status', 'Datos del agente guardados. La próxima llamada ya los usa.');
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