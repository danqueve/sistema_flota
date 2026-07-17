document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.seg[data-input]').forEach(function (contenedor) {
    var input = document.getElementById(contenedor.dataset.input);

    contenedor.querySelectorAll('button[data-value]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        contenedor.querySelectorAll('button').forEach(function (b) {
          b.classList.remove('on');
        });
        boton.classList.add('on');
        input.value = boton.dataset.value;
        input.dispatchEvent(new Event('change'));
      });
    });
  });
});
