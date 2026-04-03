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

// ===== RELAY AUTO-CONFIG =====

let autoConfigs = [];

async function fetchAutoConfig() {
    try {
        const res = await fetch(`${RELAY_API}/auto-config`);
        autoConfigs = await res.json();
        updateAutoUI();
    } catch (e) {
        // silent fail
    }
}

function updateAutoUI() {
    for (const config of autoConfigs) {
        const badge = document.getElementById(`auto-badge-${config.relay_id}`);
        const sw = document.getElementById(`relay-switch-${config.relay_id}`);
        if (badge) {
            badge.classList.toggle('hidden', !config.auto_enabled);
            if (config.auto_enabled) {
                const unit = config.sensor_type === 'light' ? 'lux' : '°C';
                badge.textContent = `AUTO (${unit})`;
            }
        }
        if (sw) sw.classList.toggle('auto-dimmed', config.auto_enabled);
    }
}

window.updateModalLabels = function () {
    const sensorType = document.getElementById('modal-sensor-type').value;
    const condition = document.getElementById('modal-condition').value;
    const unit = sensorType === 'light' ? 'lux' : '°C';

    if (condition === 'below') {
        document.getElementById('label-threshold-on').textContent = `Nyalakan jika ${unit} di bawah`;
        document.getElementById('label-threshold-off').textContent = `Matikan jika ${unit} di atas`;
    } else {
        document.getElementById('label-threshold-on').textContent = `Nyalakan jika ${unit} di atas`;
        document.getElementById('label-threshold-off').textContent = `Matikan jika ${unit} di bawah`;
    }
};

window.openAutoConfig = function (relayId) {
    const config = autoConfigs.find((c) => c.relay_id === relayId) || {};
    document.getElementById('modal-relay-id').value = relayId;
    document.getElementById('modal-relay-name').textContent = `Relay ${relayId + 1}`;
    document.getElementById('modal-auto-enabled').checked = config.auto_enabled || false;
    document.getElementById('modal-sensor-type').value = config.sensor_type || 'light';
    document.getElementById('modal-condition').value = config.condition || 'below';
    document.getElementById('modal-threshold-on').value = config.threshold_on ?? 50;
    document.getElementById('modal-threshold-off').value = config.threshold_off ?? 100;
    document.getElementById('modal-error').classList.add('hidden');
    window.updateModalLabels();
    document.getElementById('auto-modal').classList.remove('hidden');
};

window.closeAutoModal = function () {
    document.getElementById('auto-modal')?.classList.add('hidden');
};

window.saveAutoConfig = async function () {
    const relayId = parseInt(document.getElementById('modal-relay-id').value, 10);
    const autoEnabled = document.getElementById('modal-auto-enabled').checked;
    const sensorType = document.getElementById('modal-sensor-type').value;
    const condition = document.getElementById('modal-condition').value;
    const thresholdOn = parseFloat(document.getElementById('modal-threshold-on').value);
    const thresholdOff = parseFloat(document.getElementById('modal-threshold-off').value);
    const errorEl = document.getElementById('modal-error');

    if (Number.isNaN(relayId) || relayId < 0 || relayId > 3) {
        errorEl.textContent = 'Relay tidak valid';
        errorEl.classList.remove('hidden');
        return;
    }

    if (condition === 'below' && thresholdOff <= thresholdOn) {
        errorEl.textContent = 'Untuk kondisi "di bawah", threshold OFF harus lebih besar dari threshold ON';
        errorEl.classList.remove('hidden');
        return;
    }

    if (condition === 'above' && thresholdOff >= thresholdOn) {
        errorEl.textContent = 'Untuk kondisi "di atas", threshold OFF harus lebih kecil dari threshold ON';
        errorEl.classList.remove('hidden');
        return;
    }

    try {
        const res = await fetch(`${RELAY_API}/auto-config`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                relay_id: relayId,
                auto_enabled: autoEnabled,
                sensor_type: sensorType,
                condition: condition,
                threshold_on: thresholdOn,
                threshold_off: thresholdOff,
            }),
        });

        if (!res.ok) {
            let msg = 'Gagal menyimpan';
            const bodyText = await res.text();
            try {
                const json = JSON.parse(bodyText);
                const errMsg =
                    json.message ||
                    json.error ||
                    (json.errors && Object.values(json.errors).flat().join(' '));
                if (errMsg) msg = String(errMsg);
            } catch {
                if (bodyText) msg = bodyText.slice(0, 200);
            }
            throw new Error(msg);
        }

        window.closeAutoModal();
        fetchAutoConfig();
    } catch (e) {
        errorEl.textContent = e.message;
        errorEl.classList.remove('hidden');
    }
};

function initAutoModal() {
    const modal = document.getElementById('auto-modal');
    const panel = modal?.querySelector('.modal-content');
    const cancelBtn = document.getElementById('auto-modal-cancel');
    const saveBtn = document.getElementById('auto-modal-save');

    if (!modal) return;

    panel?.addEventListener('click', (e) => e.stopPropagation());

    modal.addEventListener('click', (e) => {
        if (e.target === modal) window.closeAutoModal();
    });

    cancelBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        window.closeAutoModal();
    });

    saveBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        void window.saveAutoConfig();
    });
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

// ===== TABS =====

document.querySelectorAll('#tab-bar button').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelector('#tab-bar .active').classList.remove('active');
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.getElementById('tab-dashboard').classList.toggle('hidden', tab !== 'dashboard');
        document.getElementById('tab-readings').classList.toggle('hidden', tab !== 'readings');
    });
});

// ===== INIT =====

initChart();
initTempChart();
initAutoModal();
fetchData();
fetchTempData();
fetchRelayStatus();
fetchAutoConfig();
setInterval(fetchData, 60000);
setInterval(fetchTempData, 60000);
setInterval(fetchRelayStatus, 3000);
setInterval(fetchAutoConfig, 10000);
