import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

const API = '/api/readings';
const RELAY_API = '/api/relay';
let currentRange = '24h';
let chart;

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
fetchData();
fetchRelayStatus();
setInterval(fetchData, 60000);
setInterval(fetchRelayStatus, 3000);
