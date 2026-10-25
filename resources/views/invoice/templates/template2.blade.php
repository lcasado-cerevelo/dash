<!DOCTYPE html>
<html lang="es" dir="{{ $settings['site_rtl'] == 'on' ? 'rtl' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        {{ \App\Models\Invoice::invoiceNumberFormat($invoice->invoice_id, $invoice->created_by, $invoice->workspace) }}
        |
        {{ !empty(company_setting('title_text', $invoice->created_by, $invoice->workspace)) ? company_setting('title_text', $invoice->created_by, $invoice->workspace) : (!empty(admin_setting('title_text')) ? admin_setting('title_text') : 'WorkDo') }}
    </title>
    <link
        href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap"
        rel="stylesheet">
    <style type="text/css">
        :root {
            --theme-color: #f7941d;
            --white: #ffffff;
            --black: #000000;
        }

        body {
            font-family: Arial, sans-serif;
        }

        p,
        li,
        ul,
        ol {
            margin: 0;
            padding: 0;
            list-style: none;
            line-height: 1.5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table tr th {
            padding: 0.5rem;
            text-align: left;
        }

        table tr td {
            padding: 0.5rem;
            text-align: left;
            list-style: none;
            line-height: 1.5;
            font-size: 10px;
        }

        table th small {
            display: block;
            font-size: 10px;
        }

        .text-center {
            text-align: center !important;
        }
        .invoice-preview-main {
            max-width: 750px;
            width: 100%;
            margin: 0 auto;
            background: #ffff;
            box-shadow: 0 0 10px #ddd;
        }

        .invoice-logo {
            max-width: 140px;
            width: 100%;
        }

        .invoice-header table td {
            padding: 8px 15px; 
        }


        .text-right {
            text-align: right;
        }

        .no-space tr td {
            padding: 0;
            white-space: nowrap;
        }

        .vertical-align-top td {
            vertical-align: top;
        }

        .invoice-body {
            padding: 8px 15px 0;
        }

        table.add-border tr {
            border-top: 1px solid var(--theme-color);
        }

        tfoot tr:first-of-type {
            border-bottom: 1px solid var(--theme-color);
        }

        .border-0 {
            border: none !important;
        }

        .invoice-summary td,
        .invoice-summary th {
            font-size: 10px;
            font-weight: 600;
        }

        .invoice-footer {
            padding: 15px 20px;
            font-size: 10px;
        }

        .itm-description td {
            padding-top: 0;
        }

        /* ESTILOS INNOVASYST */
        .meta-table {
            margin-top: 15px;
            margin-bottom: 10px;
            border: 1px solid var(--theme-color);
        }

        .meta-table th {
            background: var(--theme-color);
            color: #000;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 6px;
            text-align: center;
            border: 1px solid var(--theme-color);
        }

        .meta-table td {
            font-size: 9px;
            padding: 5px 6px;
            text-align: center;
            border: 1px solid var(--theme-color);
        }

        .detail-table {
            border: 1px solid var(--theme-color) !important;
        }

        .detail-table thead th {
            background: var(--theme-color) !important;
            color: #000 !important;
            border: 1px solid var(--theme-color) !important;
            font-size: 9px !important;
            padding: 5px 6px !important;
        }

        .detail-table tbody td {
            border-left: 1px solid var(--theme-color);
            border-right: 1px solid var(--theme-color);
            border-bottom: none;
            font-size: 9px;
            padding: 5px 6px;
        }

        .certification-row td {
            border: 1px solid var(--theme-color) !important;
            padding: 8px !important;
            font-size: 8px !important;
            line-height: 1.4 !important;
        }

        .signature-inline {
            margin-top: 35px;
            font-style: italic;
            color: #0066cc;
            font-size: 11px;
        }

        .summary-table {
            float: right;
            width: 35%;
            margin-top: 10px;
            font-size: 9px;
        }

        .summary-table td {
            padding: 4px 6px !important;
            border: 1px solid #000 !important;
        }

        .summary-table .label {
            text-align: right;
        }

        .summary-table .value {
            text-align: right;
            background: #fffacd;
        }

        .footer-band {
            background: var(--theme-color);
            color: #000;
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            margin-top: 15px;
            clear: both;
        }

        .footer-content {
            border: 1px solid var(--theme-color);
            border-top: none;
            padding: 6px 8px;
            font-size: 9px;
        }

        .company-info-header {
            text-align: center;
            font-size: 9px;
            line-height: 1.4;
        }

        .company-info-header .company-name {
            font-weight: bold;
            font-size: 10px;
        }

        .company-info-header .invoice-title {
            font-size: 13px;
            font-weight: bold;
            margin: 5px 0;
            color: #000;
        }

        .company-logo-text {
            font-size: 9px;
            line-height: 1.3;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="invoice-preview-main" id="boxes">
        <div class="invoice-header">
            <table>
                <tbody>
                    <tr>
                        <td style="width: 35%;">
                            <img class="invoice-logo" src="{{ $img }}" alt="">
                            <div class="company-logo-text">
                                <strong>InnovaSyst, Inc.</strong><br>
                                Praderas del Río<br>
                                3150 Río Cocal<br>
                                Toa Alta PR, 00953
                            </div>
                        </td>
                        <td class="text-right" style="width: 65%;">
                            <div class="company-info-header">
                                <div class="company-name">Ángel O. Marín</div>
                                <div style="margin: 3px 0;">
                                    T. (939)280-3362<br>
                                    amarin@innovasyst.com
                                </div>
                                <div class="invoice-title">FACTURA</div>
                                <div style="font-size: 10px;">
                                    {{ \App\Models\Invoice::invoiceNumberFormat($invoice->invoice_id, $invoice->created_by, $invoice->workspace) }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="invoice-body">
            <table>
                <tbody>
                    @if ($invoice->invoice_module != 'Fleet')
                        <tr>
                            <td style="width: 60%; font-size: 9px;">
                                @if (!empty($customer->billing_name) && !empty($customer->billing_address) && !empty($customer->billing_zip))
                                    <strong style="margin-bottom: 5px; display:block; font-size: 10px;">{{ $customer->billing_name }}</strong>
                                    <p style="font-size: 9px; line-height: 1.4;">
                                        {{ !empty($customer->billing_address) ? $customer->billing_address : '' }}<br>
                                        {{ !empty($customer->billing_city) ? $customer->billing_city . ' ,' : '' }}
                                        {{ !empty($customer->billing_state) ? $customer->billing_state . ' ,' : '' }}
                                        {{ !empty($customer->billing_zip) ? $customer->billing_zip : '' }}<br>
                                        {{ !empty($customer->billing_phone) ? $customer->billing_phone : '' }}
                                    </p>
                                @endif
                            </td>
                            <td class="text-right" style="width: 40%; font-size: 9px;">
                                <strong>Fecha:</strong> {{ fecha_es($invoice->issue_date) }}
                            </td>
                        </tr>
                    @endif

                    @if ($invoice->invoice_module == 'Fleet')
                        <tr>
                            <td style="font-size: 9px;">
                                <strong>{{ __('Name : ') }}</strong>{{ $commonCustomer['name'] }}
                            </td>
                            <td style="font-size: 9px;">
                                <strong>{{ __('Email : ') }}</strong>{{ $commonCustomer['email'] }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            {{-- TABLA METADATOS --}}
            <table class="meta-table">
                <tr>
                    <th style="width: 40%;">ATENCIÓN A</th>
                    <th style="width: 20%;">CONTRATO</th>
                    <th style="width: 20%;">MES</th>
                    <th style="width: 20%;">PERÍODO FISCAL</th>
                </tr>
                <tr>
                    <td>Departamento de Finanzas / Departamento de la Vivienda</td>
                    <td>2026-000210</td>
                    <td>noviembre 2025</td>
                    <td>2025 - 2026</td>
                </tr>
            </table>

            {{-- TABLA DETALLES --}}
            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width: 10%;" class="text-center">CANTIDAD</th>
                        <th>DESCRIPCIÓN</th>
                        <th style="width: 15%;" class="text-right">TARIFA</th>
                        <th style="width: 15%;" class="text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @if (isset($invoice->itemData) && count($invoice->itemData) > 0)
                        @foreach ($invoice->itemData as $key => $item)
                            <tr>
                                <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-right">{{ currency_format_with_sym($item->price, $invoice->created_by, $invoice->workspace) }}</td>
                                <td class="text-right">{{ currency_format_with_sym($item->price * $item->quantity, $invoice->created_by, $invoice->workspace) }}</td>
                            </tr>
                        @endforeach
                    @endif
                    
                    {{-- CERTIFICACIÓN --}}
                    <tr class="certification-row">
                        <td colspan="4">
                            Bajo pena de nulidad absoluta, certifico que ningún servidor público del Municipio de San Sebastián es parte o tiene algún interés en las ganancias o beneficios producto del contrato objeto de esta factura y de ser parte o tener interés en las ganancias o beneficios producto de compra o mercadeo una dispensa previa. La única consideración para suministrar los bienes o servicios objeto del contrato ha sido el pago acordado con el representante autorizado de la agencia. El importe de esta factura es justo y correcto. Los servicios han sido prestados y no han sido pagados.
                            <div class="signature-inline">
                                Ángel O. Marín Rolón
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            {{-- RESUMEN TOTALES --}}
            <table class="summary-table">
                <tr>
                    <td class="label">SubTotal</td>
                    <td class="value">{{ currency_format_with_sym($invoice->getSubTotal(), $invoice->created_by, $invoice->workspace) }}</td>
                </tr>
                <tr>
                    <td class="label">IVU Estatal</td>
                    <td class="value">$ -</td>
                </tr>
                <tr>
                    <td class="label">IVU Municipal</td>
                    <td class="value">$ -</td>
                </tr>
                <tr>
                    <td class="label"><strong>Total</strong></td>
                    <td class="value"><strong>{{ currency_format_with_sym($invoice->getTotal(), $invoice->created_by, $invoice->workspace) }}</strong></td>
                </tr>
            </table>

            {{-- DESCRIPCIÓN --}}
            <div class="footer-band">Descripción</div>
            <div class="footer-content">
                Suscripción - Sistema de Administración de Sección 8 (SAS8) y Manejo de Documentos (eFileHub)
            </div>

            {{-- FIRMAS --}}
            <table style="width:100%; margin-top:30px; font-size: 9px;">
                <tr>
                    <td style="width:33%; text-align:left; padding-left: 20px;">
                        <div style="border-top:1px solid #000; width:85%; display:inline-block; padding-top:5px;">
                            <strong>Ángel O. Marín Rolón</strong><br>
                            <small>Presidente</small>
                        </div>
                    </td>
                    <td style="width:33%; text-align:center;">
                        <div style="border-top:1px solid #000; margin:0 15px; padding-top:5px;">
                            Certificada Correcta
                        </div>
                    </td>
                    <td style="width:33%; text-align:center;">
                        <div style="border-top:1px solid #000; margin:0 15px; padding-top:5px;">
                            Firma
                        </div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
    @if (!isset($preview))
        @include('invoice.script');
    @endif
</body>

</html>