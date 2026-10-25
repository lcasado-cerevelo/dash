<script src="{{ asset('js/jquery.min.js') }} "></script>
<script src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>

<script>
  $(document).ready(function () {
    generatePDF();
  });

  function generatePDF() {
    var element = document.getElementById('boxes');

    var opt = {
      margin: [0.5, 0.5, 0.5, 0.5], // top, left, bottom, right (in)
      filename: '{{ \App\Models\Invoice::invoiceNumberFormat($invoice->id,$invoice->created_by)}}.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        dpi: 192,              
        letterRendering: true,
        useCORS: true
      },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', 'td'] }
    };

    html2pdf().set(opt).from(element).save().then(() => {
      closeWindow();
    });
  }

  function closeWindow() {
    setTimeout(function () {
      window.close();
    }, 1000);
  }
</script>
