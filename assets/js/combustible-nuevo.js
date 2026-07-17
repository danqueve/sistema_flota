document.addEventListener('DOMContentLoaded', function () {
  // Más de 15% por encima del promedio histórico del camión se marca como desvío.
  var UMBRAL_ALERTA = 1.15;

  var camionInput = document.getElementById('camion_id');
  var estacionInput = document.getElementById('estacion_id');
  var litrosInput = document.getElementById('litros');
  var kmInput = document.getElementById('km');
  var otraWrap = document.getElementById('estacionOtraWrap');
  var consumoCaja = document.getElementById('consumoCaja');
  var promedioCaja = document.getElementById('consumoPromedioCaja');
  var totalBar = document.getElementById('totalBarCtaCte');

  function formatearMoneda(valor) {
    return '$ ' + valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function estacionSeleccionada() {
    return (window.ESTACIONES || []).find(function (e) {
      return String(e.id) === estacionInput.value;
    });
  }

  function actualizarModalidadYTotal() {
    var estacion = estacionSeleccionada();
    var tieneCtaCte = !!(estacion && estacion.tiene_cta_cte);

    otraWrap.classList.toggle('oculto', estacionInput.value !== '');

    var botonModalidad = document.querySelector(
      '.seg[data-input="modalidad"] button[data-value="' + (tieneCtaCte ? 'cta_cte' : 'contado') + '"]'
    );
    if (botonModalidad) {
      botonModalidad.click();
    }

    if (tieneCtaCte) {
      var acumulado = (window.ACUMULADO_ESTACION || {})[estacion.id] || 0;
      document.getElementById('totalBarTexto').textContent = 'Cta. cte. ' + estacion.nombre + ' · ' + window.NOMBRE_MES_ACTUAL;
      document.getElementById('totalBarMonto').textContent = formatearMoneda(acumulado);
      totalBar.classList.remove('oculto');
    } else {
      totalBar.classList.add('oculto');
    }
  }

  function actualizarConsumo() {
    var stats = (window.STATS_CAMION || {})[camionInput.value];

    if (!stats || stats.ultimo_km === null || !litrosInput.value || !kmInput.value) {
      consumoCaja.classList.add('oculto');
      return;
    }

    var distancia = parseInt(kmInput.value, 10) - stats.ultimo_km;
    if (!(distancia > 0)) {
      consumoCaja.classList.add('oculto');
      return;
    }

    var litros = parseFloat(litrosInput.value) || 0;
    var tramo = (litros / distancia) * 100;

    document.getElementById('consumoTramo').textContent = tramo.toFixed(1).replace('.', ',') + ' L/100';

    if (stats.promedio === null) {
      document.getElementById('consumoPromedio').textContent = 'Sin historial';
      promedioCaja.classList.remove('malo');
    } else {
      var desviado = tramo > stats.promedio * UMBRAL_ALERTA;
      document.getElementById('consumoPromedio').textContent =
        stats.promedio.toFixed(1).replace('.', ',') + ' L/100' + (desviado ? ' ⚠' : '');
      promedioCaja.classList.toggle('malo', desviado);
    }

    consumoCaja.classList.remove('oculto');
  }

  camionInput.addEventListener('change', actualizarConsumo);
  estacionInput.addEventListener('change', actualizarModalidadYTotal);
  litrosInput.addEventListener('input', actualizarConsumo);
  kmInput.addEventListener('input', actualizarConsumo);

  actualizarModalidadYTotal();
  actualizarConsumo();
});
