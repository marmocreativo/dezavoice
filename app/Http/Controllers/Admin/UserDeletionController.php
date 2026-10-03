<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HardDeleteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class UserDeletionController extends Controller
{
    public function __construct(private readonly HardDeleteService $deletion) {}

    /** Vista previa: calcula qué se borraría, sin cambiar nada. */
    public function confirm(Request $request, User $user): View
    {
        $includeClients = $request->boolean('clients');
        $plan = $this->deletion->plan($user, $includeClients, $request->user());

        return view('admin.users.delete', compact('user', 'plan', 'includeClients'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'confirm_email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'confirm_email.required' => 'Escribe el correo del usuario para confirmar.',
            'password.required' => 'Escribe tu contraseña para confirmar.',
        ]);

        if (Str::lower(trim($data['confirm_email'])) !== Str::lower($user->email)) {
            return back()->withErrors(['confirm_email' => 'El correo no coincide con el del usuario.'])->withInput($request->only('clients'));
        }

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Tu contraseña no es correcta.'])->withInput($request->only('clients'));
        }

        $includeClients = $request->boolean('clients');
        $plan = $this->deletion->plan($user, $includeClients, $request->user());

        if ($plan['active_stripe'] > 0 && ! $request->boolean('stripe_ack')) {
            return back()
                ->withErrors(['stripe_ack' => 'Confirma que ya cancelaste en Stripe las suscripciones activas.'])
                ->withInput($request->only('clients'));
        }

        try {
            $result = $this->deletion->execute($user, $includeClients, $request->user(), $request->user()->adminMembership());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo eliminar y no se aplicó ningún cambio: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Usuario {$user->name} eliminado definitivamente ({$result['total']} registros).");
    }
}