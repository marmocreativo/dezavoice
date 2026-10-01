<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #091925; font-size: 12px; line-height: 1.6; }
        .header { background: #091925; padding: 20px 28px; color: #fff; }
        .header .brand { font-size: 18px; font-weight: bold; }
        .header .brand .accent { color: #FF6A00; }
        .content { padding: 28px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #6B7A87; font-size: 12px; margin-bottom: 20px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.data td { padding: 6px 0; border-bottom: 1px solid #EEF1F4; }
        table.data td.label { color: #6B7A87; width: 40%; }
        table.data td.value { font-weight: bold; }
        .clause { margin-bottom: 14px; }
        .clause .num { font-weight: bold; color: #FF6A00; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #EEF1F4; color: #9AA6B0; font-size: 10px; }
        .signature-line { margin-top: 50px; border-top: 1px solid #091925; width: 250px; padding-top: 6px; font-size: 11px; color: #6B7A87; }
    </style>
</head>
<body>
    <div class="header">
        <span class="brand">DEZA <span class="accent">Voice</span></span>
    </div>

    <div class="content">
        <h1>Contrato de Prestación de Servicios</h1>
        <p class="subtitle">DEZA Voice — Servicio de recepcionista virtual con inteligencia artificial</p>

        <table class="data">
            <tr>
                <td class="label">Cliente (razón social)</td>
                <td class="value">{{ $organization->legal_name }}</td>
            </tr>
            <tr>
                <td class="label">Nombre comercial</td>
                <td class="value">{{ $organization->name }}</td>
            </tr>
            <tr>
                <td class="label">RFC / RUC / NIF / EIN</td>
                <td class="value">{{ $organization->tax_id }}</td>
            </tr>
            <tr>
                <td class="label">Correo de facturación</td>
                <td class="value">{{ $organization->billing_email }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ $organization->address_line1 }}, {{ $organization->city }}, {{ $organization->state }}, {{ $organization->postal_code }}</td>
            </tr>
            <tr>
                <td class="label">Plan contratado</td>
                <td class="value">{{ $plan->name }}</td>
            </tr>
            <tr>
                <td class="label">Mensualidad</td>
                <td class="value">{{ $plan->currency }} {{ number_format($monthlyCents / 100, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de contratación</td>
                <td class="value">{{ $date }}</td>
            </tr>
        </table>

        <div class="clause">
            <span class="num">1.</span> El presente contrato formaliza la prestación del servicio DEZA Voice, consistente en un sistema de recepción de llamadas y gestión de comunicaciones mediante inteligencia artificial, bajo el plan <strong>{{ $plan->name }}</strong> descrito en la tabla anterior.
        </div>

        <div class="clause">
            <span class="num">2.</span> El cliente se compromete a contratar el servicio por un periodo mínimo de <strong>3 (tres) meses</strong>, contados a partir de la fecha de activación. La mensualidad se cobrará de forma automática y recurrente durante la vigencia del contrato.
        </div>

        <div class="clause">
            <span class="num">3.</span> Transcurrido el periodo mínimo forzoso, el contrato se renovará de forma automática por periodos mensuales, salvo cancelación expresa por cualquiera de las partes con al menos 15 días de anticipación.
        </div>

        <div class="clause">
            <span class="num">4.</span> El cliente podrá acceder a su portal en línea para consultar el estado de su suscripción, historial de pagos y datos de contacto asociados a su cuenta.
        </div>

        <div class="clause">
            <span class="num">5.</span> Cualquier modificación al presente contrato deberá realizarse por escrito y con la conformidad de ambas partes.
        </div>

        <div class="signature-line">Firma del cliente</div>

        <div class="footer">
            DEZA Voice · Documento generado automáticamente el {{ $date }} · Este documento tiene carácter informativo y no requiere firma para la activación del servicio.
        </div>
    </div>
</body>
</html>