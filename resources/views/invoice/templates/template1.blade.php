<!DOCTYPE html>
<html lang="en" dir="{{ $settings['site_rtl'] == 'on' ? 'rtl' : '' }}">

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
            --theme-color: {{ $color }};
            --white: #ffffff;
            --black: #000000;
        }

        body {
            font-family: 'Lato', sans-serif;
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
            padding: 0.75rem;
            text-align: left;
        }

        table tr td {
            padding: 0.75rem;
            text-align: left;
            list-style: none;
            line-height: 1.5;
            font-size: 12px;
        }

        table th small {
            display: block;
            font-size: 12px;
        }

        .text-center {
            text-align: center !important;
        }
        .invoice-preview-main {
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
            background: #ffff;
            box-shadow: 0 0 10px #ddd;
        }

        .invoice-logo {
            max-width: 200px;
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

        .view-qrcode {
            max-width: 139px;
            height: 139px;
            width: 100%;
            margin-left: auto;
            margin-top: 15px;
            background: var(--white);
            padding: 13px;
            border-radius: 10px;
        }

        .view-qrcode img {
            width: 100%;
            height: 100%;
        }

        .invoice-body {
            padding: 8px 10px 0;
        }

        table.add-border tr {
            border-top: 1px solid var(--theme-color);
        }

        tfoot tr:first-of-type {
            border-bottom: 1px solid var(--theme-color);
        }

        .total-table tr:first-of-type td {
            padding-top: 0;
        }

        .total-table tr:first-of-type {
            border-top: 0;
        }

        .sub-total {
            padding-right: 0;
            padding-left: 0;
        }

        .border-0 {
            border: none !important;
        }

        .invoice-summary td,
        .invoice-summary th {
            font-size: 12px;
            font-weight: 600;
        }

        .total-table td:last-of-type {
            width: 146px;
        }

        .invoice-footer {
            padding: 15px 20px;
        }

        .itm-description td {
            padding-top: 0;
        }

        html[dir="rtl"] table tr td,
        html[dir="rtl"] table tr th {
            text-align: right;
        }

        html[dir="rtl"] .text-right {
            text-align: left;
        }

        html[dir="rtl"] .view-qrcode {
            margin-left: 0;
            margin-right: auto;
        }

        p:not(:last-of-type) {
            margin-bottom: 8px;
        }

        .invoice-summary p {
            margin-bottom: 0;
        }

        table.add-border.invoice-summary {
            margin-top: 12px;        
        }

        .wid-75 {
            width: 75px;
        }
    </style>
</head>

<body>
    <div class="invoice-preview-main" id="boxes">
        <div class="invoice-header" style="background-color: var(--theme-color); color: {{ $font_color }};">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <img class="invoice-logo" src="{{ $img }}" alt="">
                        </td>
                        <td class="text-right">
                            <h3 style="text-transform: uppercase; font-size: 40px; font-weight: bold; ">
                                {{ __('FACTURA') }}</h3>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="vertical-align-top">
                <tbody>
                    <tr>
                        <td>
                            <p>
                                @if (!empty($settings['company_address']))
                                    {{ $settings['company_address'] }}
                                @endif
                                @if (!empty($settings['company_city']))
                                    <br> {{ $settings['company_city'] }},
                                @endif
                                @if (!empty($settings['company_state']))
                                    {{ $settings['company_state'] }}
                                @endif
                                @if (!empty($settings['company_zipcode']))
                                    - {{ $settings['company_zipcode'] }}
                                @endif
                                <br>
                                @if (!empty($settings['company_telephone']))
                                    {{ $settings['company_telephone'] }}
                                @endif
                                <br>
                                @if (!empty($settings['company_email']))
                                    {{ $settings['company_email'] }}
                                @endif
                            </p>
                        </td>
                        <td style="width: 60%;">
                            <table class="no-space">
                                <tbody>
                                    <tr>
                                        <td class="text-right"><strong>{{ __('Número: ') }}</strong></td>
                                        <td class="text-right">
                                            {{ \App\Models\Invoice::invoiceNumberFormat($invoice->invoice_id, $invoice->created_by, $invoice->workspace) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-right"><strong>{{ __('Fecha de Emsión:') }}</strong></td>
                                        <td class="text-right">
                                            {{ fecha_es($invoice->issue_date) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-right"><strong>{{ __('Fecha de Vencimiento') }}:</strong></td>
                                        <td class="text-right">
                                            {{ fecha_es($invoice->due_date) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
                            <td>

                                @if (!empty($customer->billing_name) && !empty($customer->billing_address) && !empty($customer->billing_zip))
                                    <strong style="margin-bottom: 10px; display:block;">{{ __('Facturar a') }}:</strong>
                                    <p>
                                        {{ !empty($customer->billing_name) ? $customer->billing_name : '' }}<br>
                                        {{ !empty($customer->billing_address) ? $customer->billing_address : '' }}<br>
                                        {{ !empty($customer->billing_city) ? $customer->billing_city . ' ,' : '' }}
                                        {{ !empty($customer->billing_state) ? $customer->billing_state . ' ,' : '' }}
                                        {{ !empty($customer->billing_zip) ? $customer->billing_zip : '' }}<br>
                                        {{ !empty($customer->billing_phone) ? $customer->billing_phone : '' }}<br>
                                        {{ !empty($customer->email) ? $customer->email : '' }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endif

                    @if ($invoice->invoice_module == 'Fleet')
                        <tr>
                            <td style="font-size: 13px;">
                                <label class="form-label" for="customer_name"
                                    class="form-label">{{ __('Name : ') }}</label>
                                {{ $commonCustomer['name'] }}
                            </td>
                            <td style="font-size: 13px;">
                                <label class="form-label" for="customer_name"
                                    class="form-label">{{ __('Email : ') }}</label>
                                {{ $commonCustomer['email'] }}
                            </td>
                        </tr>
                    @endif
                    @if ($invoice->invoice_module == 'sales')
                        <tr>
                            <td>
                                <strong style="margin-bottom: 10px; display:block;">{{ __('Bill To') }}:</strong>
                                <p>
                                    {{ !empty($sales_invoice->contacts->name ?? '') ? $sales_invoice->contacts->name ?? '' : '' }}<br>
                                    {{ !empty($sales_invoice->contacts->email ?? '') ? $sales_invoice->contacts->email ?? '' : '' }}<br>
                                    {{ !empty($sales_invoice->contacts->phone ?? '') ? $sales_invoice->contacts->phone ?? '' : '' }}<br>
                                    {{ !empty($sales_invoice->billing_address) ? $sales_invoice->billing_address : '' }}<br>
                                    {{ !empty($sales_invoice->billing_city) ? $sales_invoice->billing_city : '' . ', ' }}
                                    {{ !empty($sales_invoice->billing_state) ? $sales_invoice->billing_state : '' }},{{ !empty($sales_invoice->billing_country) ? $sales_invoice->billing_country : '' }}<br>
                                    {{ !empty($sales_invoice->billing_postalcode) ? $sales_invoice->billing_postalcode : '' }}
                                </p>
                            </td>
                            @if ($settings['shipping_display'] == 'on')
                                <td class="text-right">
                                    <strong style="margin-bottom: 10px; display:block;">{{ __('Ship To') }}:</strong>
                                    <p>
                                        {{ !empty($sales_invoice->shipping_contacts->name ?? '') ? $sales_invoice->shipping_contacts->name ?? '' : '' }}<br>
                                        {{ !empty($sales_invoice->shipping_contacts->email ?? '') ? $sales_invoice->shipping_contacts->email ?? '' : '' }}<br>
                                        {{ !empty($sales_invoice->shipping_contacts->phone ?? '') ? $sales_invoice->shipping_contacts->phone ?? '' : '' }}<br>
                                        {{ !empty($sales_invoice->shipping_address) ? $sales_invoice->shipping_address : '' }}<br>
                                        {{ !empty($sales_invoice->shipping_city) ? $sales_invoice->shipping_city : '' . ', ' }},{{ !empty($sales_invoice->shipping_state) ? $sales_invoice->shipping_state : '' }},{{ !empty($sales_invoice->shipping_country) ? $sales_invoice->shipping_country : '' }}<br>
                                        {{ !empty($sales_invoice->shipping_postalcode) ? $sales_invoice->shipping_postalcode : '' }}
                                    </p>
                                </td>
                            @endif
                        </tr>
                    @endif
                </tbody>
            </table>
            <table class="add-border invoice-summary" style="margin-top: 30px;">
                <thead style="background-color: var(--theme-color);color: {{ $font_color }};">
                    <tr>
                        @if ($invoice->invoice_module != 'Fleet')
                            <th class="text-center">{{ __('Concepto') }}</th>
                            <th class="text-center">{{ __('Cantidad') }}</th>
                        @endif
                        <th class="text-center">{{ __('Tarifa') }}</th>


                        <th class="text-center">{{ __('Total') }}</th>

                    </tr>
                </thead>
                <tbody>
                    @if (isset($invoice->itemData) && count($invoice->itemData) > 0)
                        @foreach ($invoice->itemData as $key => $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                @if ($invoice->invoice_module != 'Fleet')
                                    <td class="text-right">{{ $item->quantity }}</td>
                                @endif
                                <td class="text-right">{{ currency_format_with_sym($item->price, $invoice->created_by, $invoice->workspace) }}
                                </td>



                                @if ($invoice->invoice_module == 'Fleet')
                                    @php
                                        $distance = !empty($item->name) ? $item->name : 0;
                                        $price = $item->price * $item->name;
                                    @endphp
                                    <td class="text-right">{{ currency_format_with_sym($price, $invoice->created_by, $invoice->workspace) }}
                                    </td>
                                @else
                                    <td class="text-right">{{ currency_format_with_sym($item->price * $item->quantity - $item->discount + (isset($item->tax_price) ? $item->tax_price : 0), $invoice->created_by, $invoice->workspace) }}
                                    </td>
                                @endif
                                @if ($invoice->invoice_module != 'Fleet')
                                    @if ($item->description != null)
                            <tr class="border-0 itm-description ">
                                <td colspan="5">{{ $item->description }} </td>
                            </tr>
                        @endif
                    @endif
                    @endforeach
                @else
                    <tr>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>
                            <p>-</p>
                            <p>-</p>
                        </td>
                        <td>-</td>
                    <tr class="border-0 itm-description ">
                        <td colspan="5">-</td>
                    </tr>
                    </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>

                
                        {{-- Columna "Total" (texto) --}}
                        <td>{{ __('Total') }}</td>
                
                        @if ($invoice->invoice_module == 'Fleet')
                            {{-- Módulo Fleet: solamente monto total --}}
                            <td colspan="2" class="text-right">
                                <b>{{ currency_format_with_sym($invoice->totalRate, $invoice->created_by, $invoice->workspace) }}</b>
                            </td>
                        @else
                            {{-- Módulos normales (Account, etc.) --}}
                            <td class="text-right">{{ $invoice->totalQuantity }}</td>
                
                            {{-- ÚNICA celda de monto total, alineada a la derecha --}}
                            <td colspan="2" class="text-right">
                                <b>
                                    {{ currency_format_with_sym(
                                        $invoice->getSubTotal() - $invoice->getTotalDiscount() + $invoice->getTotalTax(),
                                        $invoice->created_by,
                                        $invoice->workspace
                                    ) }}
                                </b>
                            </td>
                        @endif
                    </tr>
                </tfoot>
            </table>
            @php
                $wsId = $invoice->workspace;
            
                $signerName = env('INVOICE_SIGNER_NAME_WS_'.$wsId, env('INVOICE_SIGNER_NAME', 'Nombre del firmante'));
            
                $signerTitle = env('INVOICE_SIGNER_TITLE_WS_'.$wsId, env('INVOICE_SIGNER_TITLE', 'CEO y Fundador'));
            @endphp
            <br><br><br>
            <table style="width:100%; margin-top:40px;">
                <tr>
                    <td style="width:50%; text-align:center;">
                        {{-- Línea de firma --}}
                        <div style="border-top:1px solid #000; width:80%; margin:0 auto;"></div>
                        <div style="margin-top:5px; font-size:12px;">
                            {{ $signerName }}
                        </div>
                        <div style="font-size:11px; color:#666;">
                            {{ $signerTitle }}
                        </div>
                    </td>
                    <td style="width:50%;"></td>
                </tr>
            </table>

            <div class="invoice-footer">
                <p> {{ $settings['footer_title'] }} <br>
                    {{ $settings['footer_notes'] }} </p>
            </div>
        </div>
    </div>
    @if (!isset($preview))
        @include('invoice.script');
    @endif
</body>

</html>
