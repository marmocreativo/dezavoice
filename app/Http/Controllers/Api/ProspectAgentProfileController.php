<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgentProfileRequest;
use App\Http\Resources\MenuPhotoResource;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\MenuPhoto;
use App\Models\SalesProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProspectAgentProfileController extends Controller
{
    private const FIELDS = [
        'nombre_negocio',
        'descripcion',
        'tipo_solicitud',
        'direccion',
        'horario',
        'tiempo_preparacion',
        'formas_de_pago',
        'menu',
        'instrucciones_adicionales',
    ];

    public function show(Request $request, SalesProspect $prospect): JsonResponse
    {
        $this->authorize('update', [$prospect, $request->attributes->get('activeMembership')]);

        return $this->payload($prospect);
    }

    public function update(AgentProfileRequest $request, SalesProspect $prospect): JsonResponse
    {
        $actor = $request->attributes->get('activeMembership');
        $this->authorize('update', [$prospect, $actor]);

        $profile = AgentProfile::forProspect($prospect);
        $profile->fill($request->validated());

        $dirty = $profile->getDirty();
        $before = array_intersect_key($profile->getOriginal(), $dirty);

        $profile->save();

        if ($dirty !== []) {
            AuditLog::record(
                actor: $actor,
                action: 'agent_profile.updated',
                subject: $prospect,
                before: $before,
                after: $dirty,
            );
        }

        return $this->payload($prospect);
    }

    private function payload(SalesProspect $prospect): JsonResponse
    {
        $profile = AgentProfile::forProspect($prospect);

        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $profile->{$field};
        }

        $data['photos'] = MenuPhotoResource::collection(
            MenuPhoto::where('sales_prospect_id', $prospect->id)->orderBy('id')->get(),
        )->resolve();

        return response()->json(['data' => $data]);
    }
}