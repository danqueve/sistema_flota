document.addEventListener('DOMContentLoaded', function () {
  var boton = document.getElementById('menuToggle');
  var menu = document.getElementById('appMenu');

  if (!boton || !menu) {
    return;
  }

  boton.addEventListener('click', function () {
    var abierto = menu.classList.toggle('abierto');
    boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    boton.textContent = abierto ? '✕ Cerrar' : '☰ Menú';
  });
});
