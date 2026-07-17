document.addEventListener('DOMContentLoaded', function () {
  var canvas = document.getElementById('graficoConsumo');
  if (!canvas || !window.GRAFICO_DATASETS || !window.GRAFICO_DATASETS.length) {
    return;
  }

  var colores = ['#F2B705', '#2B5FA3', '#2E7D46', '#C63C3C', '#69707A'];

  var datasets = window.GRAFICO_DATASETS.map(function (serie, indice) {
    var color = colores[indice % colores.length];
    return {
      label: serie.label,
      data: serie.data,
      borderColor: color,
      backgroundColor: color,
      spanGaps: false,
      tension: 0.25,
      pointRadius: 3,
    };
  });

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: window.GRAFICO_LABELS,
      datasets: datasets,
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: false,
          title: { display: true, text: 'L/100km' },
        },
      },
      plugins: {
        legend: { position: 'bottom' },
      },
    },
  });
});
