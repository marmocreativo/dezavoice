<?php

namespace App\Services;

class MailTemplateService
{
    /**
     * Plantilla base compartida por todos los correos transaccionales
     * de DEZA Voice, con los colores de marca.
     */
    public static function layout(string $title, string $bodyHtml): string
    {
        $safeTitle = self::e($title);

        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"></head>
        <body style=\"margin:0;padding:0;background:#EEF1F4;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;\">
            <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#EEF1F4;padding:32px 16px;\">
                <tr>
                    <td align=\"center\">
                        <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:480px;background:#FFFFFF;border-radius:14px;overflow:hidden;\">
                            <tr>
                                <td style=\"background:#091925;padding:24px 28px;\">
                                    <span style=\"color:#FFFFFF;font-size:18px;font-weight:700;\">DEZA <span style=\"color:#FF6A00;\">Voice</span></span>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding:28px;\">
                                    <h1 style=\"margin:0 0 20px;color:#091925;font-size:20px;font-weight:700;\">{$safeTitle}</h1>
                                    {$bodyHtml}
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding:20px 28px;background:#F4F6F8;\">
                                    <p style=\"margin:0;color:#9AA6B0;font-size:12px;\">DEZA Voice · Este es un correo automático, no respondas a este mensaje.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}