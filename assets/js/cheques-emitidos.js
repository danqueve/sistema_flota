document.addEventListener('DOMContentLoaded', function () {
  var dialogo = document.getElementById('dialogRechazar');
  if (!dialogo) {
    return;
  }

  document.querySelectorAll('[data-accion="rechazar"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      dialogo.querySelector('input[name="cheque_id"]').value = boton.dataset.chequeId;
      dialogo.showModal();
    });
  });

  dialogo.querySelectorAll('.btn-cerrar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      dialogo.close();
    });
  });
});
