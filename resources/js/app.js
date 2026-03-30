import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

const API = '/api/readings';
const TEMP_API = '/api/temperature';
const RELAY_API = '/api/relay';
let currentRange = '24h';
let chart;
let tempChart;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ===== LIGHT MONITOR =====

function initChart() {
    const ctx = document.getElementById('chart').getContext('2d');
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Lux',
                data: [],
                borderColor: '#4a9d56',
                backgroundColor: 'rgba(58, 125, 68, 0.1)',
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
                    backgroundColor: '#141a14',
                    titleColor: '#eee',
                    bodyColor: '#4a9d56',
                    borderColor: '#1a211a',
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
                    grid: { color: 'rgba(255, 255, 255, 0.03)' },
                    ticks: { color: '#555', maxTicksLimit: 8 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.03)' },
                    ticks: { color: '#555' },
                    title: { display: true, text: 'Lux', color: '#555' },
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
        fetchTempData();
    });
});

// ===== TEMPERATURE MONITOR =====

function initTempChart() {
    const ctx = document.getElementById('temp-chart').getContext('2d');
    tempChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Suhu (°C)',
                    data: [],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHitRadius: 8,
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y',
                },
                {
                    label: 'Kelembapan (%)',
                    data: [],
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.05)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHitRadius: 8,
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#888', boxWidth: 12 },
                },
                tooltip: {
                    backgroundColor: '#141a14',
                    titleColor: '#eee',
                    borderColor: '#1a211a',
                    borderWidth: 1,
                    callbacks: {
                        label: (ctx) => {
                            const unit = ctx.datasetIndex === 0 ? '°C' : '%';
                            return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + unit;
                        },
                    },
                },
            },
            scales: {
                x: {
                    type: 'time',
                    time: { tooltipFormat: 'dd MMM yyyy HH:mm' },
                    grid: { color: 'rgba(255, 255, 255, 0.03)' },
                    ticks: { color: '#555', maxTicksLimit: 8 },
                },
                y: {
                    position: 'left',
                    grid: { color: 'rgba(255, 255, 255, 0.03)' },
                    ticks: { color: '#f97316' },
                    title: { display: true, text: '°C', color: '#f97316' },
                },
                y1: {
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#38bdf8' },
                    title: { display: true, text: '%', color: '#38bdf8' },
                    min: 0,
                    max: 100,
                },
            },
        },
    });
}

async function fetchTempData() {
    try {
        const res = await fetch(`${TEMP_API}?range=${currentRange}&stats=1&limit=2000`);
        const json = await res.json();

        tempChart.data.datasets[0].data = json.data.map((r) => ({
            x: new Date(r.recorded_at),
            y: parseFloat(r.temperature),
        }));
        tempChart.data.datasets[1].data = json.data.map((r) => ({
            x: new Date(r.recorded_at),
            y: parseFloat(r.humidity),
        }));
        tempChart.update('none');

        if (json.latest) {
            document.getElementById('t-cur').textContent = parseFloat(json.latest.temperature).toFixed(1);
            document.getElementById('h-cur').textContent = parseFloat(json.latest.humidity).toFixed(1);
            document.getElementById('temp-dot').className = 'status-dot ok';
        }

        if (json.stats) {
            document.getElementById('t-min').textContent = json.stats.min_temp ?? '--';
            document.getElementById('t-max').textContent = json.stats.max_temp ?? '--';
            document.getElementById('h-min').textContent = json.stats.min_hum ?? '--';
            document.getElementById('h-max').textContent = json.stats.max_hum ?? '--';
        }

        const last10 = json.data.slice(-10).reverse();
        const tbody = document.getElementById('temp-tbl');
        tbody.innerHTML = last10
            .map(
                (r, i) =>
                    `<tr><td>${i + 1}</td><td class="temp-cell">${parseFloat(r.temperature).toFixed(1)}</td><td class="hum-cell">${parseFloat(r.humidity).toFixed(1)}</td><td>${r.recorded_at}</td></tr>`
            )
            .join('');
    } catch (e) {
        document.getElementById('temp-dot').className = 'status-dot err';
    }
}

// ===== RELAY CONTROL =====

function updateRelayUI(states) {
    if (!states) return;
    for (let i = 0; i < states.length; i++) {
        const checkbox = document.getElementById(`relay-${i}`);
        const status = document.getElementById(`relay-status-${i}`);
        const card = document.getElementById(`relay-card-${i}`);
        if (checkbox) checkbox.checked = states[i] === 1;
        if (status) {
            status.textContent = states[i] ? 'ON' : 'OFF';
            status.className = `relay-status ${states[i] ? 'on' : 'off'}`;
        }
        if (card) {
            card.classList.toggle('relay-active', states[i] === 1);
        }
    }
    document.getElementById('relay-dot').className = 'status-dot ok';
    document.getElementById('relay-footer').textContent =
        `Terhubung — ${new Date().toLocaleTimeString('id-ID')}`;
}

async function fetchRelayStatus() {
    try {
        const res = await fetch(`${RELAY_API}/status`);
        const json = await res.json();
        if (json.error) throw new Error(json.error);
        updateRelayUI(json.s);
    } catch (e) {
        document.getElementById('relay-dot').className = 'status-dot err';
        document.getElementById('relay-footer').textContent = 'Relay offline';
    }
}

window.toggleRelay = async function (id, checked) {
    try {
        const res = await fetch(`${RELAY_API}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ id, state: checked ? 1 : 0 }),
        });
        const json = await res.json();
        if (json.error) throw new Error(json.error);
        updateRelayUI(json.s);
    } catch (e) {
        document.getElementById('relay-dot').className = 'status-dot err';
        document.getElementById('relay-footer').textContent = 'Gagal: ' + e.message;
        fetchRelayStatus();
    }
};

window.allRelay = async function (state) {
    try {
        const res = await fetch(`${RELAY_API}/all`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ state }),
        });
        const json = await res.json();
        if (json.error) throw new Error(json.error);
        updateRelayUI(json.s);
    } catch (e) {
        document.getElementById('relay-dot').className = 'status-dot err';
        document.getElementById('relay-footer').textContent = 'Gagal: ' + e.message;
        fetchRelayStatus();
    }
};

// ===== INIT =====

initChart();
initTempChart();
fetchData();
fetchTempData();
fetchRelayStatus();
setInterval(fetchData, 60000);
setInterval(fetchTempData, 60000);
setInterval(fetchRelayStatus, 3000);
