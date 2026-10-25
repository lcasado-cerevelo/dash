<!DOCTYPE html>
<html lang="es" dir="{{ $settings['site_rtl'] == 'on' ? 'rtl' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        {{ \App\Models\Invoice::invoiceNumberFormat($invoice->id) }}
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
            margin-bottom: 100px;
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
            font-size: 10px;
            padding: 5px 6px;
            text-align: center;
            border: 1px solid var(--theme-color);
        }

        .meta-table td {
            font-size: 10px;
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
            font-size: 10px !important;
            padding: 5px 6px !important;
        }

        .detail-table tbody td {
            border-left: 1px solid var(--theme-color);
            border-right: 1px solid var(--theme-color);
            border-bottom: none;
            font-size: 10px;
            padding: 5px 6px;
        }

        .certification-row td {
            border: 1px solid var(--theme-color) !important;
            padding: 15px !important;
            font-size: 8px !important;
            line-height: 1.4 !important;
            border-top: none !important;
            text-align: justify;
        }


        .signature-inline {
            margin-top: 35px;
            font-style: italic;
            font-size: 11px;
            border-top: 1px solid #000;
        }

        .summary-table {
            font-size: 10px;
        }

        .summary-table td {
            padding: 4px 6px !important;
            border: 1px solid var(--theme-color) !important;
        }

        .summary-table .label {
            text-align: right;
            border: none !important;
        }

        .summary-table .value {
            text-align: right;
            background: #fffacd;
            
        }

        .footer-band {
            background: var(--theme-color);
            color: #000;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            margin-top: 15px;
            clear: both;
        }

        .footer-content {
            border: 1px solid var(--theme-color);
            border-top: none;
            padding: 6px 8px;
            font-size: 10px;
        }

        .company-info-header {
            text-align: center;
            font-size: 10px;
            line-height: 1.4;
            padding-top: 50px;
        }

        .company-info-header .company-name {
            
        }

        .company-info-header .invoice-title {
            font-size: 13px;
            font-weight: bold;
            margin: 5px 0;
            color: #000;
        }

        .company-logo-text {
            font-size: 10px;
            line-height: 1.3;
        }
        
        .money-cell{
            text-align:right;
            white-space:nowrap;
        }
        
        .money-cell .currency{
            display:inline-block;
            width:12px;        /* controla la separación */
            text-align:left;
            margin-right:6px;
        }
        
        .money-cell .amount{
            display:inline-block;
            min-width:70px;    /* alinea todos los números */
            text-align:right;
        }

    </style>
</head>

<body>
    <div class="invoice-preview-main" id="boxes">
        <div class="invoice-header">
            <table>
                <tbody>
                    <tr>
                        <td style="width: 33%;">
                            <img class="invoice-logo" src="{{ $img }}" alt="">
                            <div class="company-logo-text">
                                @if (!empty($settings['company_name']))
                                    <strong>{{ $settings['company_name'] }}</strong><br>
                                @endif
                                @if (!empty($settings['company_address']))
                                    {!! nl2br(e(str_replace(', ', "\n", $settings['company_address']))) !!}<br>
                                @endif
                                @if (!empty($settings['company_city']))
                                    {{ $settings['company_city'] }}, 
                                @endif
                                @if (!empty($settings['company_state']))
                                    {{ $settings['company_state'] }}
                                @endif
                                @if (!empty($settings['company_zipcode']))
                                    {{ $settings['company_zipcode'] }}
                                @endif
                            </div>
                        </td>
                        <td class="text-center" style="width: 33%; padding-left:25px">
                            <div class="company-info-header">
                                @php
                                    $wsId = $invoice->workspace;
                                    $signerName = env('INVOICE_SIGNER_NAME_WS_'.$wsId, env('INVOICE_SIGNER_NAME', 'Ángel O. Marín Rolón'));
                                @endphp
                                <div style="text-left">
                                    <div class="company-name">{{ $signerName }}</div>
                                    <div style="margin: 3px 0;">
                                        @php
                                            $phone = $settings['company_telephone'] ?? '';
                                        
                                            // Quita todo lo que no sea número
                                            $digits = preg_replace('/\D/', '', $phone);
                                        
                                            // Quita prefijo 1 si existe
                                            if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
                                                $digits = substr($digits, 1);
                                            }
                                        
                                            // Formato final
                                            if (strlen($digits) === 10) {
                                                $phoneFormatted = sprintf('(%s) %s-%s',
                                                    substr($digits, 0, 3),
                                                    substr($digits, 3, 3),
                                                    substr($digits, 6)
                                                );
                                            } else {
                                                $phoneFormatted = $phone; // fallback
                                            }
                                        @endphp                                    
                                        {{ $phoneFormatted }} <br>
                                        @if (!empty($settings['company_email']))
                                            {{ $settings['company_email'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-right" style="width: 33%;">
                                <div class="invoice-title"><strong>FACTURA</strong></div>
                                <div style="font-size: 10px;">
                                    {{ \App\Models\Invoice::invoiceNumberFormat($invoice->id) }}
                                </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="invoice-body">
            <table style="margin-top:50px;">
                <tbody>
                    @if ($invoice->invoice_module != 'Fleet')
                        <tr>
                            <td style="width: 70%; font-size: 12px; padding-left:50px;">
                                @if (!empty($customer->shipping_name) && !empty($customer->shipping_address) && !empty($customer->shipping_zip))
                                    <p style="font-size: 12px; line-height: 1.4;">
                                        <strong>
                                            {!! nl2br(e(str_replace('- ', "\n", $customer->shipping_name))) !!}
                                        </strong><br>
                                        
                                        @if (!empty($customer->shipping_address))
                                            {!! nl2br(e(str_replace(', ', "\n", $customer->shipping_address))) !!}<br>
                                        @endif
                                        
                                        @if (!empty($customer->shipping_city))
                                            {{ $customer->shipping_city }},
                                        @endif
                                        @if (!empty($customer->shipping_state))
                                            {{ $customer->shipping_state }}
                                        @endif
                                        @if (!empty($customer->shipping_zip))
                                            {{ $customer->shipping_zip }}
                                        @endif
                                    </p><br>
                                    @php
                                        $shipPhone = $customer->shipping_phone ?? '';
                                    
                                        $digits = preg_replace('/\D/', '', $shipPhone);
                                    
                                        if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
                                            $digits = substr($digits, 1);
                                        }
                                    
                                        if (strlen($digits) === 10) {
                                            $shipPhoneFormatted = sprintf('(%s) %s-%s',
                                                substr($digits, 0, 3),
                                                substr($digits, 3, 3),
                                                substr($digits, 6)
                                            );
                                        } else {
                                            $shipPhoneFormatted = $shipPhone;
                                        }
                                    @endphp   
                                    
                                    {{ $shipPhoneFormatted }}
                                @endif
                            </td>
                            <td class="text-right" style="width: 30%; font-size: 12px;">
                                <strong>Fecha:</strong> {{ fecha_es($invoice->issue_date) }}
                            </td>
                        </tr>
                    @endif

                    @if ($invoice->invoice_module == 'Fleet')
                        <tr>
                            <td style="font-size: 12px;">
                                <strong>{{ __('Name : ') }}</strong>{{ $commonCustomer['name'] }}
                            </td>
                            <td style="font-size: 11px;">
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
                    <td>{{ $invoice->attention_to ?? 'Departamento de Finanzas / Departamento de la Vivienda' }}</td>
                    <td>{{ $invoice->contract_number }}</td>
                    <td>{{ $invoice->fiscal_month }}</td>
                    <td>{{ $invoice->fiscal_period }}</td>
                </tr>
            </table>

            {{-- TABLA DETALLES --}}
            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width: 10%;" class="text-center">CANTIDAD</th>
                        <th class="text-center">DESCRIPCIÓN</th>
                        <th style="width: 10%;" class="text-center">TARIFA</th>
                        <th style="width: 15%;" class="text-center">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @if (isset($invoice->itemData) && count($invoice->itemData) > 0)
                        @foreach ($invoice->itemData as $key => $item)
                            <tr>
                                <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="money-cell">
                                    <span class="currency">$</span>
                                    <span class="amount">
                                        {{ number_format($item->price, 2) }}
                                    </span>
                                </td>
                                <td class="money-cell">
                                    <span class="currency">$</span>
                                    <span class="amount">
                                        {{ number_format($item->price * $item->quantity, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    
                    {{-- CERTIFICACIÓN --}}
                    <tr class="certification-row">
                        <td></td>
                        <td>
                          @php
                            $custName = !empty($customer->billing_name) ? $customer->billing_name : 'Municipio';
                        
                            $defaultNote = 'Bajo pena de nulidad absoluta, certifico que ningún servidor público del '
                              . $custName
                              . ' es parte o tiene algún interés en las ganancias o beneficios producto del contrato objeto de esta factura y de ser parte o tener interés en las ganancias o beneficios producto de compra o mercadeo una dispensa previa. La única consideración para suministrar los bienes o servicios objeto del contrato ha sido el pago acordado con el representante autorizado de la agencia. El importe de esta factura es justo y correcto. Los servicios han sido prestados y no han sido pagados.';
                        
                            $wsId = $invoice->workspace;
                            $signerName = env('INVOICE_SIGNER_NAME_WS_'.$wsId, env('INVOICE_SIGNER_NAME', 'Ángel O. Marín Rolón'));
                          @endphp
                        
                        <div style="padding-left: 18px; padding-right: 18px;">
                          <p style="margin:0 0 16px 0; line-height:1.35;">
                            {{ $invoice->invoice_note ?? '' }}
                          </p>
                        
                          <div style="position: relative; margin-top: 6px; height: 45px;">
                            
                            <!-- Firma -->
                            <img src="{{ asset('storage/app/public/signatures/firma_angel.svg') }}"
                                 alt="Firma"
                                 style="
                                    position:absolute;
                                    left:0;
                                    top:0px;
                                    width:175px;
                                    height:auto;
                                    z-index:1;
                                    background-color: transparent;
                                 ">
                        
                            <!-- Línea encima (no se oculta jamás) -->
                            <div style="
                                position:absolute;
                                left:0;
                                right:0;
                                top:31px;              /* ajusta para que cruce la firma */
                                border-top:1px solid #000;
                                z-index:2;
                            ">{{ $signerName }}</div>
                        
                          </div>
                        
                          <div style="margin-top: 2px;">
                            
                          </div>
                        </div>
                        
                        </td>
                        <td></td><td></td>
                    </tr>
                </tbody>
                <tfoot>
                </tfoot>
            </table>

            {{-- RESUMEN TOTALES --}}
            <table class="summary-table">
                <tr>
                    <td class="label" style="width:10%"></td>
                    <td class="label"></td>
                    <td style="width:10%" class="label">Sub Total</td>
                    <td style="width:15%; border-top:none !important"class="value money-cell">
                        <span class="currency">$</span>
                        <span class="amount">
                            {{ number_format($invoice->getSubTotal(), 2) }}
                        </span>
                    </td>
                </tr>
                @if ($invoice->getTotalDiscount())
                    <tr>
                        <td class="label" style="width:10%"></td>
                        <td colspan="2" class="label" style="width:10%">Descuento</td>
                        <td class="value money-cell">
                            <span class="currency">$</span>
                            <span class="amount">
                                {{ number_format($invoice->getTotalDiscount(), 2) }}
                            </span>
                        </td>
                    </tr>
                @endif
                @if (!empty($invoice->taxesData))
                    @foreach ($invoice->taxesData as $taxName => $taxPrice)
                        <tr>
                            <td class="label" style="width:10%"></td>
                            <td colspan="2" class="label">{{ $taxName }}</td>
                            <td class="value money-cell">
                                <span class="currency">$</span>
                                <span class="amount">
                                    {{ number_format($taxPrice, 2) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                @endif
                <tr>
                    <td class="label" style="width:10%"></td>
                    <td colspan="2" class="label"><strong>Total</strong></td>
                    <td class="value money-cell">
                        <strong>
                                <span class="currency">$</span>
                                <span class="amount">
                                    {{ number_format($invoice->getSubTotal() - $invoice->getTotalDiscount() + $invoice->getTotalTax(), 2) }}
                                </span>
                        </strong>
                    </td>
                </tr>
            </table>

            {{-- DESCRIPCIÓN --}}
            <div class="footer-band">Descripción</div>
            <div class="footer-content">
                {{ $invoice->invoice_description ?? 'Suscripción - Sistema de Administración de Sección 8 (SAS8) y Manejo de Documentos (eFileHub)' }}
            </div>

            {{-- FIRMAS --}}
            @php
                $wsId = $invoice->workspace;
                $signerName = env('INVOICE_SIGNER_NAME_WS_'.$wsId, env('INVOICE_SIGNER_NAME', 'Ángel O. Marín Rolón'));
                $signerTitle = env('INVOICE_SIGNER_TITLE_WS_'.$wsId, env('INVOICE_SIGNER_TITLE', 'Presidente'));
            @endphp
<table style="width:100%; margin-top:80px; font-size: 10px;">
  <tr>
    <td style="width:20%; text-align:left; padding-left: 20px;">
      <div style="width:auto; display:inline-block; padding-top:5px;">
        <strong>{{ $signerName }}</strong><br>
        <small>{{ $signerTitle }}</small>
      </div>
    </td>

    <td style="text-align:left; padding-left:100px;">
      <!-- Caja fija como antes -->
      <div style="position: relative; width:350px; margin:0 15px; height:48px;">

        <!-- Firma (capa abajo) -->
        <img src="{{ asset('storage/app/public/signatures/firma_angel.svg') }}"
             alt="Firma"
             style="
                position:absolute;
                left:0;
                top:-2px;        /* ajusta altura de la firma */
                width:250px;
                height:auto;
                z-index:1;
             ">

        <!-- Línea encima (capa arriba, no se tapa jamás) -->
        <div style="
            position:absolute;
            left:0;
            right:0;
            top:43px;           /* ajusta para que cruce la firma */
            border-top:1px solid #000;
            z-index:2;
        "></div>

        <!-- Textos debajo de la línea -->
        <div style="
            position:absolute;
            left:0;
            right:0;
            top:41px;           /* debajo de la línea */
            display:flex;
            justify-content:space-between;
            padding-top:3px;
            z-index:3;
        ">
          <span>Certificada Correcta</span>
          <span style="margin-right:100px;">Firma</span>
        </div>

      </div>
    </td>
  </tr>
</table>
<div style="margin-top:25px;border-top: 2px solid var(--theme-color);width: 100%;"></div>
        </div>
    </div>
    @if (!isset($preview))
        @include('invoice.script');
    @endif
</body>

</html>