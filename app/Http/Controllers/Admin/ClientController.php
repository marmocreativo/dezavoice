<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SalesProspect;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function edit(Organization $organization): View
    {
        return view('admin.clients.edit', [
            'organization' => $organization,
            'users' => $this->clientUsers($organization),
            'prospect' => $this->prospectFor($organization),
        ]);
    }

    public function update(ClientRequest $request, Organization $organization): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user()->adminMembership();

        DB::transaction(function () use ($data, $organization, $actor) {
            $before = [];
            $after = [];

            $organization->fill(Arr::except($data, ['users']));

            if ($organization->isDirty()) {
                $before['organization'] = array_intersect_key($organization->getOriginal(), $organization->getDirty());
                $after['organization'] = $organization->getDirty();
                $organization->save();
            }

            foreach ($this->clientUsers($organization) as $user) {
                $input = $data['users'][$user->uuid] ?? null;

                if (! $input) {
                    continue;
                }

                $user->fill($input);

                if ($user->isDirty()) {
                    $before['users'][$user->uuid] = array_intersect_key($user->getOriginal(), $user->getDirty());
                    $after['users'][$user->uuid] = $user->getDirty();
                    $user->save();
                }
            }

            if ($after !== []) {
                AuditLog::record(
                    actor: $actor,
                    action: 'client.updated',
                    subject: $organization,
                    before: $before,
                    after: $after,
                );
            }
        });

        $prospect = $this->prospectFor($organization);

        return redirect()
            ->to($prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index'))
            ->with('status', 'Datos del cliente actualizados.');
    }

    private function clientUsers(Organization $organization)
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q->where('role', 'client')->where('organization_id', $organization->id))
            ->orderBy('name')
            ->get();
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