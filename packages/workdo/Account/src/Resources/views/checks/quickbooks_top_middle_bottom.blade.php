<!--
File: resources/views/accounting/checks/quickbooks_top_middle_bottom.blade.php
Descripción:
 - Plantilla estilo QuickBooks: cheque arriba (top), talonario del beneficiario en el medio (middle), talonario del emisor abajo (bottom).
 - Se asume papel preimpreso con MICR y diseño de banco.
 - Solo se imprime en el CHEQUE: fecha, nombre del beneficiario y cantidad (en palabras y en números).
 - En los TALONARIOS (ambos iguales): monto del bill, monto retenido por tax (valor negativo) y monto neto del cheque.

Variables esperadas:
 - $payment: objeto con ->date (Carbon), ->amount (neto del cheque), ->memo (opcional), ->check_no (opcional), ->vendor (relación con ->name)
 - $billAmount: decimal — monto total del bill (bruto)
 - $taxWithheld: decimal negativo — retención (por ejemplo -123.45)
 - $amountWords: string — cantidad en palabras para el cheque (en ES o EN)
 - (opcional) $companyName, $companyAddress para talonarios

Nota:
 - Alturas QuickBooks típicas: cada sección ~3.5in en una hoja Letter (11in). Ajusta offsets según tu papel.
 - Impresión sin márgenes: configura el generador PDF para margin 0 (DOMPDF o Snappy/wkhtmltopdf).
-->
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Cheque {{ $payment->check_no ?? '' }}</title>
<style>
  @page { size: Letter; margin: 0; }
  html, body { margin:0; padding:0; width:8.5in; height:11in; font-family: Arial, Helvetica, sans-serif; }
  .page { position: relative; width:8.5in; height:11in; }
  .segment { position: absolute; left:0; width:8.5in; height:3.5in; }
  /* Zonas (ajusta tops para tu stock): */
  #cheque  { top:0in; }
  #stub1   { top:3.5in; }
  #stub2   { top:7.0in; }

  /* Helpers de texto */
  .small { font-size: 10pt; }
  .base  { font-size: 11pt; }
  .big   { font-size: 12pt; }
  .bold  { font-weight: bold; }

  /* Campos del cheque — posiciones típicas QuickBooks Top Check */
  .chk-date    { position:absolute; top:0.60in; right:0.65in; font-size: 11pt; }
  .chk-payee   { position:absolute; top:1.05in; left:0.70in;  font-size: 12pt; width:5.8in; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .chk-amount  { position:absolute; top:1.05in; right:0.65in; font-size: 12pt; }
  .chk-words   { position:absolute; top:1.50in; left:0.70in; right:0.65in; font-size: 11pt; }
  .chk-memo    { position:absolute; bottom:0.55in; left:0.80in; font-size: 10pt; max-width:4in; }

  /* Talonarios (formato de tabla simple con posiciones internas) */
  .stub-wrap { position:absolute; left:0.50in; top:0.55in; right:0.50in; }
  .stub-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.20in; }
  .stub-company { max-width:4.6in; }
  .stub-company .name { font-weight:bold; font-size:12pt; }
  .stub-company .addr { font-size:10pt; line-height:1.25; }
  .stub-check { text-align:right; font-size:10pt; }
  .hr { height:1px; background:#000; opacity:0.3; margin:0.08in 0; }
  table.stub { width:100%; border-collapse: collapse; font-size: 11pt; }
  table.stub th, table.stub td { text-align:left; padding:6px 4px; }
  table.stub th { border-bottom:1px solid #333; font-weight:600; }
  table.stub tfoot td { border-top:1px solid #333; font-weight:bold; }
  .right { text-align:right; }

  /* Tip: muestra líneas guía durante calibración (descomenta) */
  /* .segment { outline: 1px dashed #999; } */
</style>
</head>
<body>
  @php
    // Formateadores
    $fmtDate  = optional($payment->date)->format('m/d/Y');
    $payee    = $payment->vendor->name ?? '';
    $net      = $payment->amount ?? 0.00; // Neto del cheque
    $gross    = isset($billAmount) ? (float)$billAmount : $net - ((float)($taxWithheld ?? 0));
    $tax      = isset($taxWithheld) ? (float)$taxWithheld : 0.00; // esperado negativo

    $money = function($v){ return number_format((float)$v, 2, '.', ','); };

    // Etiquetas genericas de talonario
    $stubTitle = 'Payment Advice';
    $billLbl   = 'Bill Amount';
    $taxLbl    = 'Tax Withheld';
    $netLbl    = 'Net Check';

    // Datos de empresa opcionales
    $companyTitle = $companyName ?? config('app.name');
    $companyAddr  = $companyAddress ?? '';
  @endphp

  <div class="page">

    <!-- Cheque (arriba) -->
    <section id="cheque" class="segment">
      <div class="chk-date">{{ $fmtDate }}</div>
      <div class="chk-payee">{{ $payee }}</div>
      <div class="chk-amount">${{ $money($net) }}</div>
      <div class="chk-words">{{ $amountWords }}</div>
      @if(!empty($payment->memo))
        <div class="chk-memo">{{ 'Memo: '.$payment->memo }}</div>
      @endif
    </section>

    <!-- Talonario del beneficiario (middle) -->
    <section id="stub1" class="segment">
      <div class="stub-wrap">
        <div class="stub-header">
          <div class="stub-company">
            <div class="name">{{ $companyTitle }}</div>
            @if(!empty($companyAddr))
              <div class="addr">{!! nl2br(e($companyAddr)) !!}</div>
            @endif
          </div>
          <div class="stub-check">
            <div><span class="bold">Date:</span> {{ $fmtDate }}</div>
            @if(!empty($payment->check_no))
              <div><span class="bold">Check #:</span> {{ $payment->check_no }}</div>
            @endif
            <div><span class="bold">Payee:</span> {{ $payee }}</div>
            <div><span class="bold">Amount:</span> ${{ $money($net) }}</div>
          </div>
        </div>
        <div class="hr"></div>
        <table class="stub">
          <thead>
            <tr>
              <th>Concept</th>
              <th class="right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ $billLbl }}</td>
              <td class="right">${{ $money($gross) }}</td>
            </tr>
            <tr>
              <td>{{ $taxLbl }}</td>
              <td class="right">${{ $money($tax) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td>{{ $netLbl }}</td>
              <td class="right">${{ $money($net) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- Talonario del emisor (bottom) -->
    <section id="stub2" class="segment">
      <div class="stub-wrap">
        <div class="stub-header">
          <div class="stub-company">
            <div class="name">{{ $companyTitle }}</div>
            @if(!empty($companyAddr))
              <div class="addr">{!! nl2br(e($companyAddr)) !!}</div>
            @endif
          </div>
          <div class="stub-check">
            <div><span class="bold">Date:</span> {{ $fmtDate }}</div>
            @if(!empty($payment->check_no))
              <div><span class="bold">Check #:</span> {{ $payment->check_no }}</div>
            @endif
            <div><span class="bold">Payee:</span> {{ $payee }}</div>
            <div><span class="bold">Amount:</span> ${{ $money($net) }}</div>
          </div>
        </div>
        <div class="hr"></div>
        <table class="stub">
          <thead>
            <tr>
              <th>Concept</th>
              <th class="right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ $billLbl }}</td>
              <td class="right">${{ $money($gross) }}</td>
            </tr>
            <tr>
              <td>{{ $taxLbl }}</td>
              <td class="right">${{ $money($tax) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td>{{ $netLbl }}</td>
              <td class="right">${{ $money($net) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

  </div>
</body>
</html>
