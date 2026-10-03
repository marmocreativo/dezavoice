<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserCredentialsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPasswordController extends Controller
{
    public function __construct(private readonly UserCredentialsService $credentials) {}

    /** Genera una contraseña temporal, cierra sus sesiones y se la envía por correo. */
    public function send(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes reiniciar tu propia contraseña desde aquí. Usa "Editar usuario" para fijarla.');
        }

        $temporaryPassword = Str::random(10);
        $revoked = 0;

        DB::transaction(function () use ($request, $user, $temporaryPassword, &$revoked) {
            $user->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'remember_token' => Str::random(60), // invalida los "recordarme"
            ])->save();

            // Sesiones abiertas en la PWA (tokens) y en el panel (sesiones en base de datos).
            $revoked = $user->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            AuditLog::record(
                actor: $request->user()->adminMembership(),
                action: 'user.password_reset',
                subject: $user,
                before: [],
                after: ['method' => 'email', 'sessions_revoked' => $revoked],
            );
        });

        // El correo se envía con la transacción ya confirmada.
        try {
            $this->credentials->send($user, $temporaryPassword);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.users.show', $user->uuid)
                ->with('warning', "La contraseña se cambió, pero no se pudo enviar el correo a {$user->email}. Contraseña temporal: {$temporaryPassword} (compártela de forma segura).");
        }

        return redirect()
            ->route('admin.users.show', $user->uuid)
            ->with('status', "Contraseña nueva enviada a {$user->email}. Tendrá que cambiarla al entrar.");
    }
}