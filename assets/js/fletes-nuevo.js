document.addEventListener('DOMContentLoaded', function () {
  var pct = parseFloat(document.getElementById('pctComisionVigente').value) || 0;
  var importeInput = document.getElementById('importe_bruto');
  var comisionTexto = document.getElementById('comisionCalculada');

  function formatearMoneda(valor) {
    return '$ ' + valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function actualizar() {
    var importe = parseFloat(importeInput.value) || 0;
    comisionTexto.textContent = formatearMoneda(importe * pct / 100);
  }

  importeInput.addEventListener('input', actualizar);
  actualizar();
});
