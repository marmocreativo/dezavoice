<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UserCredentialsService
{
    /** Envía una contraseña temporal a un usuario que ya existe. */
    public function send(User $user, string $temporaryPassword): void
    {
        $loginUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/').'/login';

        $bodyHtml = '
            <p style="margin:0 0 16px;">Hola '.MailTemplateService::e($user->name).',</p>
            <p style="margin:0 0 16px;">Se actualizaron tus datos de acceso a DEZA Voice. Esta es tu contraseña temporal:</p>
            <p style="margin:0 0 20px;"><span style="font-family:\'Courier New\',monospace;font-size:18px;font-weight:700;letter-spacing:1px;color:#091925;">'.MailTemplateService::e($temporaryPassword).'</span></p>
            <p style="margin:0 0 20px;"><a href="'.MailTemplateService::e($loginUrl).'" style="display:inline-block;padding:14px 28px;background:#FF6A00;color:#FFFFFF;font-weight:600;font-size:15px;text-decoration:none;border-radius:8px;">Iniciar sesión</a></p>
            <p style="margin:0;color:#6B7A87;font-size:13px;">Se te pedirá que cambies tu contraseña la primera vez que ingreses.</p>
        ';

        $html = MailTemplateService::layout(title: 'Tus datos de acceso — DEZA Voice', bodyHtml: $bodyHtml);

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)->subject('Tus datos de acceso — DEZA Voice');
        });
    }
}