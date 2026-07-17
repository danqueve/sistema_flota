document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('formFiltros');
  if (!form) {
    return;
  }

  form.querySelectorAll('.filtro-auto').forEach(function (campo) {
    campo.addEventListener('change', function () {
      form.submit();
    });
  });
});
