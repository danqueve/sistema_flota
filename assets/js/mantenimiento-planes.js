document.addEventListener('DOMContentLoaded', function () {
  var camionInput = document.getElementById('camion_id');
  var tipoInput = document.getElementById('tipo_service_id');
  var autoHistorial = document.getElementById('autoHistorial');
  var autoHistorialTexto = document.getElementById('autoHistorialTexto');
  var puntoPartidaWrap = document.getElementById('puntoPartidaWrap');

  if (!camionInput || !tipoInput || !autoHistorial || !puntoPartidaWrap) {
    return;
  }

  function actualizar() {
    if (!camionInput.value || !tipoInput.value) {
      autoHistorial.classList.add('oculto');
      puntoPartidaWrap.classList.add('oculto');
      return;
    }

    var ultimo = (window.ULTIMO_SERVICIO || {})[camionInput.value + '_' + tipoInput.value];

    if (ultimo) {
      var fecha = new Date(ultimo.fecha + 'T00:00:00');
      var fechaTexto = fecha.toLocaleDateString('es-AR');
      autoHistorialTexto.textContent = fechaTexto + (ultimo.km ? ' · km ' + Number(ultimo.km).toLocaleString('es-AR') : '');
      autoHistorial.classList.remove('oculto');
      puntoPartidaWrap.classList.add('oculto');
    } else {
      autoHistorial.classList.add('oculto');
      puntoPartidaWrap.classList.remove('oculto');
    }
  }

  camionInput.addEventListener('change', actualizar);
  tipoInput.addEventListener('change', actualizar);
  actualizar();
});
