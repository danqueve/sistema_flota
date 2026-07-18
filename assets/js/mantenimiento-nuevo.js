document.addEventListener('DOMContentLoaded', function () {
  var camionInput = document.getElementById('camion_id');
  var kmInput = document.getElementById('km');
  var kmActualTexto = document.getElementById('kmActualTexto');
  var kmConfirmadoWrap = document.getElementById('kmConfirmadoWrap');
  var generarEgreso = document.getElementById('generar_egreso');
  var cuentaWrap = document.getElementById('cuentaWrap');

  function actualizarKm() {
    var kmActual = (window.KM_ACTUAL_CAMION || {})[camionInput.value];

    if (kmActual === null || kmActual === undefined) {
      kmActualTexto.textContent = '';
      kmConfirmadoWrap.classList.add('oculto');
      return;
    }

    kmActualTexto.textContent = 'Km actual del camión: ' + Number(kmActual).toLocaleString('es-AR');

    var km = kmInput.value !== '' ? parseInt(kmInput.value, 10) : null;
    kmConfirmadoWrap.classList.toggle('oculto', !(km !== null && km < kmActual));
  }

  function actualizarEgreso() {
    cuentaWrap.classList.toggle('oculto', !generarEgreso.checked);
  }

  camionInput.addEventListener('change', actualizarKm);
  kmInput.addEventListener('input', actualizarKm);
  generarEgreso.addEventListener('change', actualizarEgreso);

  actualizarKm();
  actualizarEgreso();
});
