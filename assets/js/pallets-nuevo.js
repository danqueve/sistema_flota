document.addEventListener('DOMContentLoaded', function () {
  var tipoInput = document.getElementById('tipo');
  var clienteInput = document.getElementById('cliente_id');
  var disponibleWrap = document.getElementById('disponibleWrap');

  function actualizarDisponible() {
    var stock = (window.STOCK_POR_CLIENTE || {})[clienteInput.value] || { sanos: 0, rotos: 0, reacondicionados: 0, separadores: 0 };

    document.getElementById('dispSanos').textContent = stock.sanos;
    document.getElementById('dispRotos').textContent = stock.rotos;
    document.getElementById('dispReacondicionados').textContent = stock.reacondicionados;
    document.getElementById('dispSeparadores').textContent = stock.separadores;

    disponibleWrap.classList.toggle('oculto', tipoInput.value !== 'devolucion');
  }

  clienteInput.addEventListener('change', actualizarDisponible);

  document.querySelectorAll('.seg[data-input="tipo"] button').forEach(function (boton) {
    boton.addEventListener('click', actualizarDisponible);
  });

  actualizarDisponible();
});
