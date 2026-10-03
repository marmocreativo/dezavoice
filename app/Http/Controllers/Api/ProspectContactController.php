<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesContactResource;
use App\Models\SalesContact;
use App\Models\SalesProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProspectContactController extends Controller
{
    public function store(Request $request, SalesProspect $prospect): JsonResponse
    {
        $this->authorizeProspect($request, $prospect);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $contact = DB::transaction(function () use ($prospect, $data) {
            // El primer contacto queda como principal; uno nuevo marcado como principal desplaza al anterior.
            $makePrimary = ! empty($data['is_primary']) || ! $prospect->contacts()->exists();

            if ($makePrimary) {
                $prospect->contacts()->update(['is_primary' => false]);
            }

            return $prospect->contacts()->create([...$data, 'is_primary' => $makePrimary]);
        });

        return (new SalesContactResource($contact))->response()->setStatusCode(201);
    }

    public function update(Request $request, SalesContact $contact): SalesContactResource
    {
        $this->authorizeProspect($request, $contact->prospect);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($contact, $data) {
            if (! empty($data['is_primary'])) {
                SalesContact::where('sales_prospect_id', $contact->sales_prospect_id)
                    ->whereKeyNot($contact->id)
                    ->update(['is_primary' => false]);
            }

            $contact->update($data);
        });

        return new SalesContactResource($contact->fresh());
    }

    public function destroy(Request $request, SalesContact $contact): JsonResponse
    {
        $this->authorizeProspect($request, $contact->prospect);

        $contact->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function authorizeProspect(Request $request, SalesProspect $prospect): void
    {
        $this->authorize('update', [$prospect, $request->attributes->get('activeMembership')]);
    }
}