import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

const API = '/api/readings';
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
                backgroundColor: 'rgba(245, 166, 35, 0.08)',
                borderWidth: 2,
                pointRadius: 0,
                pointHitRadius: 8,
                fill: true,
                tension: 0.3,
            }],
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
                        label: (ctx) => ctx.parsed.y.toFixed(1) + ' lux',
                    },
                },
            },
            scales: {
                x: {
                    type: 'time',
                    time: { tooltipFormat: 'dd MMM yyyy HH:mm' },
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: { color: '#666', maxTicksLimit: 8 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: { color: '#666' },
                    title: { display: true, text: 'Lux', color: '#666' },
                },
            },
        },
    });
}

async function fetchData() {
    try {
        const res = await fetch(`${API}?range=${currentRange}&stats=1&limit=2000`);
        const json = await res.json();

        chart.data.datasets[0].data = json.data.map((r) => ({
            x: new Date(r.recorded_at),
            y: parseFloat(r.lux),
        }));
        chart.update('none');

        if (json.latest) {
            document.getElementById('c-cur').textContent = parseFloat(json.latest.lux).toFixed(1);
            document.getElementById('dot').className = 'status-dot ok';
        }

        if (json.stats) {
            document.getElementById('c-min').textContent = json.stats.min_lux ?? '--';
            document.getElementById('c-max').textContent = json.stats.max_lux ?? '--';
            document.getElementById('c-avg').textContent = json.stats.avg_lux ?? '--';
        }

        const last10 = json.data.slice(-10).reverse();
        const tbody = document.getElementById('tbl');
        tbody.innerHTML = last10
            .map(
                (r, i) =>
                    `<tr><td>${i + 1}</td><td class="lux-cell">${parseFloat(r.lux).toFixed(1)}</td><td>${r.recorded_at}</td></tr>`
            )
            .join('');

        document.getElementById('footer').textContent =
            `Terakhir diperbarui: ${new Date().toLocaleTimeString('id-ID')} — auto-refresh 60 detik`;
    } catch (e) {
        document.getElementById('dot').className = 'status-dot err';
        document.getElementById('footer').textContent = 'Gagal mengambil data: ' + e.message;
    }
}

document.querySelectorAll('#range-bar button').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelector('#range-bar .active').classList.remove('active');
        btn.classList.add('active');
        currentRange = btn.dataset.range;
        fetchData();
    });
});

initChart();
fetchData();
setInterval(fetchData, 60000);
