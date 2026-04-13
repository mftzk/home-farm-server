import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

const API = '/api/readings';
const TEMP_API = '/api/temperature';
const RELAY_API = '/api/relay';
const INSIGHT_API = '/api/insight/daily';
let currentRange = '24h';
let currentTempMetric = 'avg'; // 'min' | 'avg' | 'max' — only relevant for 7d/30d
let lastTempData = null;       // cache last fetched temp data to avoid re-fetch on metric switch
let chart;
let tempChart;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ===== MODE (READ / EDIT) =====

const MODE_API = '/api/mode';
let currentMode = window.__dashboardMode || 'read';

function updateModeUI() {
    const isEdit = currentMode === 'edit';
    const icon = document.getElementById('mode-icon');
    const label = document.getElementById('mode-label');
    const btn = document.getElementById('mode-toggle');

    if (icon) icon.innerHTML = isEdit ? '&#128275;' : '&#128274;';
    if (label) label.textContent = isEdit ? 'Mode Edit' : 'Mode Baca';
    if (btn) btn.classList.toggle('edit-active', isEdit);

    document.querySelectorAll('[data-edit-only]').forEach(el => {
        el.classList.toggle('disabled', !isEdit);
        if (el.tagName === 'BUTTON') el.disabled = !isEdit;
        if (el.tagName === 'LABEL') {
            const input = el.querySelector('input');
            if (input) input.disabled = !isEdit;
        }
    });
}

window.handleModeToggle = function () {
    if (currentMode === 'edit') {
        lockMode();
    } else {
        openPinModal();
    }
};

function openPinModal() {
    const modal = document.getElementById('pin-modal');
    const input = document.getElementById('pin-input');
    const error = document.getElementById('pin-error');
    if (modal) modal.style.display = '';
    if (input) { input.value = ''; input.focus(); }
    if (error) error.classList.add('hidden');
}

function closePinModal() {
    const modal = document.getElementById('pin-modal');
    if (modal) modal.style.display = 'none';
}

async function submitPin() {
    const pin = document.getElementById('pin-input')?.value || '';
    const errorEl = document.getElementById('pin-error');

    if (!/^\d{6}$/.test(pin)) {
        if (errorEl) {
            errorEl.textContent = 'PIN harus 6 digit angka';
            errorEl.classList.remove('hidden');
        }
        return;
    }

    try {
        const res = await fetch(`${MODE_API}/verify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({ pin }),
        });

        if (!res.ok) {
            const json = await res.json().catch(() => ({}));
            throw new Error(json.error || 'PIN salah');
        }

        const json = await res.json();
        currentMode = json.mode;
        updateModeUI();
        closePinModal();
    } catch (e) {
        if (errorEl) {
            errorEl.textContent = e.message;
            errorEl.classList.remove('hidden');
        }
    }
}

async function lockMode() {
    try {
        const res = await fetch(`${MODE_API}/lock`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
        });
        const json = await res.json();
        currentMode = json.mode;
    } catch {
        currentMode = 'read';
    }
    updateModeUI();
}

function initPinModal() {
    const modal = document.getElementById('pin-modal');
    const panel = modal?.querySelector('.modal-content');
    const cancelBtn = document.getElementById('pin-modal-cancel');
    const submitBtn = document.getElementById('pin-modal-submit');
    const input = document.getElementById('pin-input');

    panel?.addEventListener('click', (e) => e.stopPropagation());
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closePinModal();
    });
    cancelBtn?.addEventListener('click', () => closePinModal());
    submitBtn?.addEventListener('click', () => submitPin());
    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') submitPin();
    });
}

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

const AGGREGATED_RANGES = ['7d', '30d'];

function updateTempMetricBar() {
    const bar = document.getElementById('temp-metric-bar');
    if (!bar) return;
    if (AGGREGATED_RANGES.includes(currentRange)) {
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
    }
}

document.querySelectorAll('#temp-metric-bar button').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelector('#temp-metric-bar .active')?.classList.remove('active');
        btn.classList.add('active');
        currentTempMetric = btn.dataset.metric;
        if (lastTempData) applyTempMetric(lastTempData); // re-render from cache, no re-fetch
    });
});

document.querySelectorAll('#range-bar button').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelector('#range-bar .active').classList.remove('active');
        btn.classList.add('active');
        currentRange = btn.dataset.range;
        updateTempMetricBar();
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

function applyTempMetric(json) {
    const isAggregated = AGGREGATED_RANGES.includes(currentRange);
    const metric = isAggregated ? currentTempMetric : 'avg';

    const tempField = metric === 'min' ? 'min_temp' : metric === 'max' ? 'max_temp' : 'temperature';
    const humField  = metric === 'min' ? 'min_hum'  : metric === 'max' ? 'max_hum'  : 'humidity';

    const metricLabel = metric === 'min' ? 'Min ' : metric === 'max' ? 'Max ' : '';
    tempChart.data.datasets[0].label = `${metricLabel}Suhu (°C)`;
    tempChart.data.datasets[1].label = `${metricLabel}Kelembapan (%)`;

    tempChart.data.datasets[0].data = json.data.map((r) => ({
        x: new Date(r.recorded_at),
        y: parseFloat(r[tempField]),
    }));
    tempChart.data.datasets[1].data = json.data.map((r) => ({
        x: new Date(r.recorded_at),
        y: parseFloat(r[humField]),
    }));
    tempChart.update('none');

    const last10 = json.data.slice(-10).reverse();
    const tbody = document.getElementById('temp-tbl');
    tbody.innerHTML = last10
        .map(
            (r, i) =>
                `<tr><td>${i + 1}</td><td class="temp-cell">${parseFloat(r[tempField]).toFixed(1)}</td><td class="hum-cell">${parseFloat(r[humField]).toFixed(1)}</td><td>${r.recorded_at}</td></tr>`
        )
        .join('');
}

async function fetchTempData() {
    try {
        const res = await fetch(`${TEMP_API}?range=${currentRange}&stats=1&limit=2000`);
        const json = await res.json();

        lastTempData = json;
        applyTempMetric(json);

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

    } catch (e) {
        document.getElementById('temp-dot').className = 'status-dot err';
    }
}

// ===== RELAY AUTO-CONFIG =====

let autoConfigs = [];
let currentModalRelayId = null;

function normalizeAutoConfig(config) {
    return {
        ...config,
        relay_id: Number(config.relay_id),
        auto_enabled: config.auto_enabled === true || config.auto_enabled === 1 || config.auto_enabled === '1',
    };
}

function upsertAutoConfig(config) {
    const normalized = normalizeAutoConfig(config);
    const index = autoConfigs.findIndex((item) => item.relay_id === normalized.relay_id);

    if (index === -1) {
        autoConfigs.push(normalized);
    } else {
        autoConfigs[index] = normalized;
    }
}

async function fetchAutoConfig() {
    try {
        const res = await fetch(`${RELAY_API}/auto-config`, {
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
            },
        });
        const configs = await res.json();
        autoConfigs = Array.isArray(configs) ? configs.map(normalizeAutoConfig) : [];
        updateAutoUI();
    } catch (e) {
        // silent fail
    }
}

function updateAutoUI() {
    for (let relayId = 0; relayId < 4; relayId++) {
        const badge = document.getElementById(`auto-badge-${relayId}`);
        const sw = document.getElementById(`relay-switch-${relayId}`);
        if (badge) badge.classList.add('hidden');
        if (sw) sw.classList.remove('auto-dimmed');
    }

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
    if (currentMode !== 'edit') return;
    currentModalRelayId = relayId;
    const config = autoConfigs.find((c) => c.relay_id === relayId) || {};
    document.getElementById('modal-relay-name').textContent = `Relay ${relayId + 1}`;
    document.getElementById('modal-auto-enabled').checked = config.auto_enabled || false;
    document.getElementById('modal-sensor-type').value = config.sensor_type || 'light';
    document.getElementById('modal-condition').value = config.condition || 'below';
    document.getElementById('modal-threshold-on').value = config.threshold_on ?? 50;
    document.getElementById('modal-threshold-off').value = config.threshold_off ?? 100;
    document.getElementById('modal-error').classList.add('hidden');
    window.updateModalLabels();
    document.getElementById('auto-modal').style.display = '';
};

window.closeAutoModal = function () {
    const modal = document.getElementById('auto-modal');
    if (modal) modal.style.display = 'none';
    currentModalRelayId = null;
};

window.saveAutoConfig = async function () {
    const relayId = currentModalRelayId;
    const autoEnabled = document.getElementById('modal-auto-enabled').checked;
    const sensorType = document.getElementById('modal-sensor-type').value;
    const condition = document.getElementById('modal-condition').value;
    const thresholdOn = parseFloat(document.getElementById('modal-threshold-on').value);
    const thresholdOff = parseFloat(document.getElementById('modal-threshold-off').value);
    const errorEl = document.getElementById('modal-error');

    errorEl.classList.add('hidden');

    if (autoEnabled && condition === 'below' && thresholdOff <= thresholdOn) {
        errorEl.textContent = 'Untuk kondisi "di bawah", threshold OFF harus lebih besar dari threshold ON';
        errorEl.classList.remove('hidden');
        return;
    }

    if (autoEnabled && condition === 'above' && thresholdOff >= thresholdOn) {
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

        if (res.status === 403) {
            currentMode = 'read';
            updateModeUI();
            window.closeAutoModal();
            throw new Error('Mode edit diperlukan');
        }

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

        const savedConfig = normalizeAutoConfig(await res.json());
        upsertAutoConfig(savedConfig);
        updateAutoUI();
        window.closeAutoModal();
        void fetchAutoConfig();
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
        if (res.status === 403) {
            currentMode = 'read';
            updateModeUI();
            throw new Error('Mode edit diperlukan');
        }
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
        if (res.status === 403) {
            currentMode = 'read';
            updateModeUI();
            throw new Error('Mode edit diperlukan');
        }
        const json = await res.json();
        if (json.error) throw new Error(json.error);
        updateRelayUI(json.s);
    } catch (e) {
        document.getElementById('relay-dot').className = 'status-dot err';
        document.getElementById('relay-footer').textContent = 'Gagal: ' + e.message;
        fetchRelayStatus();
    }
};

// ===== DAILY INSIGHT =====

function trendHtml(trend) {
    if (!trend || trend.percent === 0 || trend.direction === 'stable') {
        return '<span class="trend-stable">&#9644; stabil vs kemarin</span>';
    }
    if (trend.direction === 'up') {
        return `<span class="trend-up">&#9650; ${trend.percent}%</span> <span class="text-xs text-gray-600">vs kemarin</span>`;
    }
    return `<span class="trend-down">&#9660; ${trend.percent}%</span> <span class="text-xs text-gray-600">vs kemarin</span>`;
}

function formatVal(val, decimals = 1) {
    return val !== null && val !== undefined ? parseFloat(val).toFixed(decimals) : '--';
}

function timeLabel(t) {
    return t ? `(${t})` : '';
}

function updateInsightSection(prefix, data, unit) {
    document.getElementById(`insight-${prefix}-avg`).textContent = formatVal(data.today.avg);
    document.getElementById(`insight-${prefix}-trend`).innerHTML = trendHtml(data.trend);
    document.getElementById(`insight-${prefix}-max`).textContent =
        data.today.max !== null ? `${formatVal(data.today.max)} ${unit}` : '--';
    document.getElementById(`insight-${prefix}-max-at`).textContent = timeLabel(data.today.max_at);
    document.getElementById(`insight-${prefix}-min`).textContent =
        data.today.min !== null ? `${formatVal(data.today.min)} ${unit}` : '--';
    document.getElementById(`insight-${prefix}-min-at`).textContent = timeLabel(data.today.min_at);

    if (data.last24h) {
        document.getElementById(`insight-${prefix}-24h-max`).textContent =
            data.last24h.max !== null ? `${formatVal(data.last24h.max)} ${unit}` : '--';
        document.getElementById(`insight-${prefix}-24h-max-at`).textContent = timeLabel(data.last24h.max_at);
        document.getElementById(`insight-${prefix}-24h-min`).textContent =
            data.last24h.min !== null ? `${formatVal(data.last24h.min)} ${unit}` : '--';
        document.getElementById(`insight-${prefix}-24h-min-at`).textContent = timeLabel(data.last24h.min_at);
    }
}

function updateRelayInsight(relay) {
    const el = document.getElementById('insight-relay');
    if (!relay) {
        el.innerHTML = '<span class="text-xs text-gray-600">Relay: data tidak tersedia</span>';
        return;
    }

    const autoConfigs = (relay.configs || []).filter((c) => c.auto_enabled);
    let badges = autoConfigs
        .map((c) => {
            const unit = c.sensor_type === 'light' ? 'lux' : '°C';
            return `<span class="insight-relay-badge">R${c.relay_id + 1}: AUTO (${unit})</span>`;
        })
        .join('');

    el.innerHTML =
        `<span class="text-xs text-gray-500 font-semibold">Relay: ${relay.auto_enabled_count}/${relay.total} auto aktif</span>` +
        (badges ? `<span class="insight-relay-badges">${badges}</span>` : '');
}

async function fetchInsight() {
    try {
        const res = await fetch(INSIGHT_API);
        const json = await res.json();

        const today = new Date();
        const options = { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' };
        document.getElementById('insight-date').textContent = today.toLocaleDateString('id-ID', options);

        updateInsightSection('light', json.light, 'lux');
        updateInsightSection('temp', json.temperature, '°C');
        updateInsightSection('hum', json.humidity, '%');
        updateRelayInsight(json.relay);
    } catch (e) {
        document.getElementById('insight-date').textContent = 'Gagal memuat insight';
    }
}

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
initPinModal();
updateModeUI();
fetchData();
fetchTempData();
fetchRelayStatus();
fetchAutoConfig();
fetchInsight();
setInterval(fetchData, 60000);
setInterval(fetchTempData, 60000);
setInterval(fetchRelayStatus, 3000);
setInterval(fetchAutoConfig, 10000);
setInterval(fetchInsight, 300000);
