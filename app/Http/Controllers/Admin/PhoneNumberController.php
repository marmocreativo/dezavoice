<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PhoneNumberRequest;
use App\Models\AuditLog;
use App\Models\ClientPhoneNumber;
use App\Models\Organization;
use App\Models\RetellCall;
use App\Models\SalesProspect;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhoneNumberController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('admin.phones.index', [
            'organization' => $organization,
            'prospect' => $this->prospectFor($organization),
            'numbers' => ClientPhoneNumber::where('organization_id', $organization->id)->orderBy('phone_number')->get(),
            'calls' => RetellCall::where('organization_id', $organization->id)->latest()->limit(10)->get(),
            'inboundUrl' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/retell/inbound',
        ]);
    }

    public function store(PhoneNumberRequest $request, Organization $organization): RedirectResponse
    {
        $number = ClientPhoneNumber::create([
            'organization_id' => $organization->id,
            ...$request->validated(),
            'is_active' => true,
        ]);

        AuditLog::record(
            actor: $request->user()->adminMembership(),
            action: 'phone_number.created',
            subject: $organization,
            before: [],
            after: ['phone_number' => $number->phone_number],
        );

        return redirect()
            ->route('admin.organizations.phones.index', $organization->uuid)
            ->with('status', "Número {$number->phone_number} asignado a {$organization->name}.");
    }

    public function toggle(Request $request, ClientPhoneNumber $phone): RedirectResponse
    {
        $phone->update(['is_active' => ! $phone->is_active]);

        AuditLog::record(
            actor: $request->user()->adminMembership(),
            action: 'phone_number.toggled',
            subject: $phone->organization,
            before: ['is_active' => ! $phone->is_active],
            after: ['phone_number' => $phone->phone_number, 'is_active' => $phone->is_active],
        );

        return back()->with('status', $phone->is_active ? 'Número activado.' : 'Número desactivado: las llamadas se rechazarán.');
    }

    public function destroy(Request $request, ClientPhoneNumber $phone): RedirectResponse
    {
        $organization = $phone->organization;

        AuditLog::record(
            actor: $request->user()->adminMembership(),
            action: 'phone_number.deleted',
            subject: $organization,
            before: ['phone_number' => $phone->phone_number],
            after: [],
        );

        $phone->delete();

        return redirect()
            ->route('admin.organizations.phones.index', $organization->uuid)
            ->with('status', 'Número eliminado. Las llamadas a ese número se rechazarán.');
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