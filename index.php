<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Light Monitor Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#1a1a2e;color:#eee;min-height:100vh}
.wrap{max-width:960px;margin:0 auto;padding:24px 16px}
h1{text-align:center;font-size:1.5em;margin-bottom:20px;color:#f5a623}
h1 span{font-size:.6em;color:#666;display:block;font-weight:normal;margin-top:4px}

/* range buttons */
.range-bar{display:flex;justify-content:center;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.range-bar button{background:#16213e;color:#aaa;border:2px solid #16213e;border-radius:8px;
  padding:8px 18px;cursor:pointer;font-size:.9em;transition:.2s}
.range-bar button:hover{border-color:#f5a623;color:#eee}
.range-bar button.active{background:#f5a623;color:#1a1a2e;border-color:#f5a623;font-weight:bold}

/* cards row */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:20px}
.card{background:#16213e;border-radius:14px;padding:20px;text-align:center;
  box-shadow:0 4px 20px rgba(0,0,0,.3)}
.card .label{font-size:.8em;color:#888;margin-bottom:6px}
.card .value{font-size:1.8em;font-weight:bold}
.card .unit{font-size:.7em;color:#888}
.card.current .value{color:#f5a623}
.card.min .value{color:#0984e3}
.card.max .value{color:#e17055}
.card.avg .value{color:#00b894}

/* chart */
.chart-box{background:#16213e;border-radius:14px;padding:20px;margin-bottom:20px;
  box-shadow:0 4px 20px rgba(0,0,0,.3)}
.chart-box canvas{width:100%!important;max-height:340px}

/* table */
.table-box{background:#16213e;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.3);overflow-x:auto}
.table-box h2{font-size:1em;color:#aaa;margin-bottom:12px}
table{width:100%;border-collapse:collapse;font-size:.9em}
th,td{padding:10px 14px;text-align:left}
th{color:#888;border-bottom:1px solid #253350;font-weight:600}
td{border-bottom:1px solid #1a1a2e}
tr:hover td{background:#1a1a2e}
.lux-cell{color:#f5a623;font-weight:bold}

.footer{text-align:center;margin-top:20px;font-size:.75em;color:#444}
.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}
.status-dot.ok{background:#00b894}.status-dot.err{background:#e17055}
</style>
</head>
<body>
<div class="wrap">

<h1>&#9728; Light Monitor Dashboard
  <span>ESP8266 + BH1750 &mdash; Time Series</span>
</h1>

<div class="range-bar">
  <button data-range="1h">1 Jam</button>
  <button data-range="6h">6 Jam</button>
  <button data-range="24h" class="active">24 Jam</button>
  <button data-range="7d">7 Hari</button>
  <button data-range="30d">30 Hari</button>
</div>

<div class="cards">
  <div class="card current">
    <div class="label"><span class="status-dot ok" id="dot"></span>Terakhir</div>
    <div class="value" id="c-cur">--</div>
    <div class="unit">lux</div>
  </div>
  <div class="card min">
    <div class="label">Min</div>
    <div class="value" id="c-min">--</div>
    <div class="unit">lux</div>
  </div>
  <div class="card max">
    <div class="label">Max</div>
    <div class="value" id="c-max">--</div>
    <div class="unit">lux</div>
  </div>
  <div class="card avg">
    <div class="label">Rata-rata</div>
    <div class="value" id="c-avg">--</div>
    <div class="unit">lux</div>
  </div>
</div>

<div class="chart-box">
  <canvas id="chart"></canvas>
</div>

<div class="table-box">
  <h2>10 Pembacaan Terbaru</h2>
  <table>
    <thead><tr><th>#</th><th>Lux</th><th>Waktu</th></tr></thead>
    <tbody id="tbl"></tbody>
  </table>
</div>

<div class="footer" id="footer">Auto-refresh 60 detik</div>

</div>

<script>
const API = 'api/readings.php';
let currentRange = '24h';
let chart;

function initChart() {
  const ctx = document.getElementById('chart').getContext('2d');
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      datasets: [{
        label: 'Lux',
        data: [],
        borderColor: '#f5a623',
        backgroundColor: 'rgba(245,166,35,0.08)',
        borderWidth: 2,
        pointRadius: 0,
        pointHitRadius: 8,
        fill: true,
        tension: 0.3,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#16213e',
          titleColor: '#eee',
          bodyColor: '#f5a623',
          borderColor: '#253350',
          borderWidth: 1,
          callbacks: {
            label: ctx => ctx.parsed.y.toFixed(1) + ' lux'
          }
        }
      },
      scales: {
        x: {
          type: 'time',
          time: { tooltipFormat: 'dd MMM yyyy HH:mm' },
          grid: { color: 'rgba(255,255,255,0.04)' },
          ticks: { color: '#666', maxTicksLimit: 8 }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(255,255,255,0.04)' },
          ticks: { color: '#666' },
          title: { display: true, text: 'Lux', color: '#666' }
        }
      }
    }
  });
}

async function fetchData() {
  try {
    const res = await fetch(`${API}?range=${currentRange}&stats=1&limit=2000`);
    const json = await res.json();

    // chart
    chart.data.datasets[0].data = json.data.map(r => ({
      x: new Date(r.recorded_at),
      y: parseFloat(r.lux)
    }));
    chart.update('none');

    // cards
    if (json.latest) {
      document.getElementById('c-cur').textContent = parseFloat(json.latest.lux).toFixed(1);
      document.getElementById('dot').className = 'status-dot ok';
    }
    if (json.stats) {
      document.getElementById('c-min').textContent = json.stats.min_lux ?? '--';
      document.getElementById('c-max').textContent = json.stats.max_lux ?? '--';
      document.getElementById('c-avg').textContent = json.stats.avg_lux ?? '--';
    }

    // table (last 10, reversed so newest first)
    const last10 = json.data.slice(-10).reverse();
    const tbody = document.getElementById('tbl');
    tbody.innerHTML = last10.map((r, i) =>
      `<tr><td>${i + 1}</td><td class="lux-cell">${parseFloat(r.lux).toFixed(1)}</td><td>${r.recorded_at}</td></tr>`
    ).join('');

    document.getElementById('footer').textContent =
      `Terakhir diperbarui: ${new Date().toLocaleTimeString('id-ID')} — auto-refresh 60 detik`;
  } catch (e) {
    document.getElementById('dot').className = 'status-dot err';
    document.getElementById('footer').textContent = 'Gagal mengambil data: ' + e.message;
  }
}

// range buttons
document.querySelectorAll('.range-bar button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelector('.range-bar .active').classList.remove('active');
    btn.classList.add('active');
    currentRange = btn.dataset.range;
    fetchData();
  });
});

initChart();
fetchData();
setInterval(fetchData, 60000);
</script>
</body>
</html>
