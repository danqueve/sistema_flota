document.addEventListener('DOMContentLoaded', function () {
  var buscador = document.getElementById('buscador');
  if (buscador) {
    buscador.addEventListener('input', function () {
      var termino = buscador.value.trim().toLowerCase();
      document.querySelectorAll('#listaRepuestos .item').forEach(function (item) {
        var texto = item.dataset.busqueda || '';
        item.classList.toggle('oculto', termino !== '' && texto.indexOf(termino) === -1);
      });
    });
  }

  var dialogo = document.getElementById('dialogUsar');
  if (!dialogo) {
    return;
  }

  var titulo = document.getElementById('usarRepuestoNombre');
  var repuestoIdInput = document.getElementById('usar_repuesto_id');

  document.querySelectorAll('[data-accion="usar"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      repuestoIdInput.value = boton.dataset.repuestoId;
      titulo.textContent = boton.dataset.repuestoNombre;
      document.getElementById('usar_cantidad').value = '';
      dialogo.showModal();
    });
  });

  dialogo.querySelectorAll('.btn-cerrar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      dialogo.close();
    });
  });
});
