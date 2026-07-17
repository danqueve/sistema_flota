document.addEventListener('DOMContentLoaded', function () {
  function abrirDialogo(dialogo, chequeId) {
    dialogo.querySelector('input[name="cheque_id"]').value = chequeId;
    dialogo.querySelectorAll('.seg[data-input]').forEach(function (seg) {
      seg.querySelectorAll('button').forEach(function (b) {
        b.classList.remove('on');
      });
      document.getElementById(seg.dataset.input).value = '';
    });
    dialogo.showModal();
  }

  document.querySelectorAll('[data-accion="depositar"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      abrirDialogo(document.getElementById('dialogDepositar'), boton.dataset.chequeId);
    });
  });

  document.querySelectorAll('[data-accion="endosar"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      abrirDialogo(document.getElementById('dialogEndosar'), boton.dataset.chequeId);
    });
  });

  document.querySelectorAll('[data-accion="rechazar"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      abrirDialogo(document.getElementById('dialogRechazar'), boton.dataset.chequeId);
    });
  });

  var dialogoVender = document.getElementById('dialogVender');
  var montoNetoInput = document.getElementById('monto_neto');
  var descuentoTexto = document.getElementById('descuentoVenta');

  function actualizarDescuento() {
    var importe = parseFloat(dialogoVender.dataset.importe) || 0;
    var neto = parseFloat(montoNetoInput.value) || 0;
    descuentoTexto.textContent = '$ ' + (importe - neto).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  document.querySelectorAll('[data-accion="vender"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      dialogoVender.dataset.importe = boton.dataset.importe;
      montoNetoInput.value = '';
      abrirDialogo(dialogoVender, boton.dataset.chequeId);
      actualizarDescuento();
    });
  });
  montoNetoInput.addEventListener('input', actualizarDescuento);

  document.querySelectorAll('dialog .btn-cerrar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      boton.closest('dialog').close();
    });
  });
});
