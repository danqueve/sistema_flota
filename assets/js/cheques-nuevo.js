document.addEventListener('DOMContentLoaded', function () {
  var clienteInput = document.getElementById('cliente_id');
  var fleteInput = document.getElementById('flete_id');
  var fleteWrap = document.getElementById('fleteWrap');
  var fleteSeg = document.getElementById('fleteSeg');
  var fechaPagoInput = document.getElementById('fecha_pago');
  var diasChip = document.getElementById('diasCobro');

  function renderFletes() {
    var fletes = (window.FLETES_POR_CLIENTE || {})[clienteInput.value] || [];
    fleteSeg.innerHTML = '';

    var botonNinguno = document.createElement('button');
    botonNinguno.type = 'button';
    botonNinguno.dataset.value = '';
    botonNinguno.textContent = 'Ninguno';
    botonNinguno.className = 'on';
    fleteSeg.appendChild(botonNinguno);

    fletes.forEach(function (f) {
      var boton = document.createElement('button');
      boton.type = 'button';
      boton.dataset.value = f.id;
      boton.textContent = f.label;
      fleteSeg.appendChild(boton);
    });

    fleteInput.value = '';
    fleteWrap.classList.toggle('oculto', fletes.length === 0);

    fleteSeg.querySelectorAll('button').forEach(function (boton) {
      boton.addEventListener('click', function () {
        fleteSeg.querySelectorAll('button').forEach(function (b) {
          b.classList.remove('on');
        });
        boton.classList.add('on');
        fleteInput.value = boton.dataset.value;
      });
    });
  }

  function actualizarDias() {
    if (!fechaPagoInput.value) {
      diasChip.textContent = '';
      return;
    }
    var hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    var pago = new Date(fechaPagoInput.value + 'T00:00:00');
    var dias = Math.round((pago - hoy) / 86400000);

    if (dias < 0) {
      diasChip.textContent = 'vencido hace ' + Math.abs(dias) + ' días';
    } else if (dias === 0) {
      diasChip.textContent = 'cobra hoy';
    } else {
      diasChip.textContent = dias + ' días al cobro';
    }
  }

  clienteInput.addEventListener('change', renderFletes);
  fechaPagoInput.addEventListener('change', actualizarDias);

  renderFletes();
  actualizarDias();
});
