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

  var dialogo = document.getElementById('dialogMovimiento');
  var tituloDialogo = document.getElementById('movimientoRepuestoNombre');
  var repuestoIdInput = document.getElementById('movimiento_repuesto_id');
  var etiquetaCantidad = document.getElementById('etiquetaCantidad');
  var camionWrap = document.getElementById('camionWrap');

  function actualizarPorTipo() {
    var tipo = document.getElementById('mov_tipo').value;
    etiquetaCantidad.textContent = tipo === 'ajuste' ? 'Stock real (conteo físico)' : 'Cantidad';
    camionWrap.classList.toggle('oculto', tipo !== 'egreso');
  }

  document.querySelectorAll('[data-accion="movimiento"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      repuestoIdInput.value = boton.dataset.repuestoId;
      tituloDialogo.textContent = boton.dataset.repuestoNombre;
      document.querySelectorAll('.seg[data-input="mov_tipo"] button').forEach(function (b) {
        b.classList.remove('on');
      });
      dialogo.querySelector('.seg[data-input="mov_tipo"] button[data-value="ingreso"]').classList.add('on');
      document.getElementById('mov_tipo').value = 'ingreso';
      document.getElementById('mov_cantidad').value = '';
      actualizarPorTipo();
      dialogo.showModal();
    });
  });

  document.getElementById('mov_tipo').addEventListener('change', actualizarPorTipo);

  dialogo.querySelectorAll('.btn-cerrar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      dialogo.close();
    });
  });
});
