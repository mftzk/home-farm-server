<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>IoT Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-dark-900 text-gray-100 min-h-screen font-sans">
    <div class="max-w-[1100px] mx-auto px-4 py-6">

        <h1 class="text-center text-2xl font-bold text-white mb-1">
            IoT<span class="text-forest-light">Dashboard</span>
        </h1>
        <p class="text-center text-sm text-gray-600 mb-5">
            ESP8266 &mdash; Light Monitor + Temp/Humidity + Relay Control
        </p>

        {{-- Tab navigation --}}
        <div class="flex justify-center gap-2 mb-5" id="tab-bar">
            <button data-tab="dashboard" class="tab-btn active">Dashboard</button>
            <button data-tab="readings" class="tab-btn">Pembacaan</button>
        </div>

        {{-- Tab: Dashboard --}}
        <div id="tab-dashboard">

        {{-- Range buttons --}}
        <div class="flex justify-center gap-2 mb-5 flex-wrap" id="range-bar">
            <button data-range="1h" class="range-btn">1 Jam</button>
            <button data-range="6h" class="range-btn">6 Jam</button>
            <button data-range="24h" class="range-btn active">24 Jam</button>
            <button data-range="7d" class="range-btn">7 Hari</button>
            <button data-range="30d" class="range-btn">30 Hari</button>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-5">
            <div class="card">
                <div class="card-label">
                    <span class="status-dot ok" id="dot"></span>Terakhir
                </div>
                <div class="card-value text-forest-light" id="c-cur">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Min</div>
                <div class="card-value text-emerald-300" id="c-min">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Max</div>
                <div class="card-value text-green-200" id="c-max">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Rata-rata</div>
                <div class="card-value text-teal-300" id="c-avg">--</div>
                <div class="card-unit">lux</div>
            </div>
        </div>

        {{-- Temperature & Humidity stat cards --}}
        <h2 class="section-title">Suhu & Kelembapan (SHT40)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3.5 mb-5">
            <div class="card">
                <div class="card-label">
                    <span class="status-dot ok" id="temp-dot"></span>Suhu
                </div>
                <div class="card-value text-orange-400" id="t-cur">--</div>
                <div class="card-unit">&deg;C</div>
            </div>
            <div class="card">
                <div class="card-label">Min Suhu</div>
                <div class="card-value text-orange-300" id="t-min">--</div>
                <div class="card-unit">&deg;C</div>
            </div>
            <div class="card">
                <div class="card-label">Max Suhu</div>
                <div class="card-value text-orange-200" id="t-max">--</div>
                <div class="card-unit">&deg;C</div>
            </div>
            <div class="card">
                <div class="card-label">Kelembapan</div>
                <div class="card-value text-sky-400" id="h-cur">--</div>
                <div class="card-unit">%</div>
            </div>
            <div class="card">
                <div class="card-label">Min Hum</div>
                <div class="card-value text-sky-300" id="h-min">--</div>
                <div class="card-unit">%</div>
            </div>
            <div class="card">
                <div class="card-label">Max Hum</div>
                <div class="card-value text-sky-200" id="h-max">--</div>
                <div class="card-unit">%</div>
            </div>
        </div>

        {{-- Temperature chart --}}
        <div class="panel mb-5">
            <canvas id="temp-chart"></canvas>
        </div>

        {{-- Light stat cards title --}}
        <h2 class="section-title">Cahaya (BH1750)</h2>

        {{-- Chart (left) + Relay (right) --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4 mb-5">

            {{-- Chart --}}
            <div class="panel">
                <canvas id="chart"></canvas>
            </div>

            {{-- Relay Control --}}
            <div class="panel relay-panel">
                <h2 class="text-sm font-bold text-gray-300 mb-3">Relay Control</h2>

                <div class="grid grid-cols-2 gap-2.5 mb-3" id="relay-cards">
                    @for ($i = 0; $i < 4; $i++)
                    <div class="relay-card" id="relay-card-{{ $i }}">
                        <div class="relay-card-header">
                            <div class="card-label">Relay {{ $i + 1 }}</div>
                            <div class="relay-card-actions">
                                <span class="auto-badge hidden" id="auto-badge-{{ $i }}">AUTO</span>
                                <button class="gear-btn" onclick="window.openAutoConfig({{ $i }})" title="Auto-mode settings">&#9881;</button>
                            </div>
                        </div>
                        <label class="relay-switch" id="relay-switch-{{ $i }}">
                            <input type="checkbox" id="relay-{{ $i }}" onchange="window.toggleRelay({{ $i }}, this.checked)">
                            <span class="relay-slider"></span>
                        </label>
                        <div class="relay-status off" id="relay-status-{{ $i }}">OFF</div>
                    </div>
                    @endfor
                </div>

                <div class="flex justify-center gap-2 mb-3">
                    <button class="relay-btn relay-btn-on" onclick="window.allRelay(1)">ALL ON</button>
                    <button class="relay-btn relay-btn-off" onclick="window.allRelay(0)">ALL OFF</button>
                </div>

                <div class="text-center">
                    <span class="status-dot" id="relay-dot"></span>
                    <span class="text-xs text-gray-600" id="relay-footer">Menghubungkan ke relay...</span>
                </div>
            </div>

        </div>

        </div>{{-- /tab-dashboard --}}

        {{-- Tab: Readings (hidden by default) --}}
        <div id="tab-readings" class="hidden">
            {{-- Temperature Table --}}
            <div class="panel overflow-x-auto mb-5">
                <h2 class="text-sm text-gray-500 mb-3 uppercase tracking-wider">10 Pembacaan Suhu Terbaru</h2>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="table-th">#</th>
                            <th class="table-th">Suhu (&deg;C)</th>
                            <th class="table-th">Kelembapan (%)</th>
                            <th class="table-th">Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="temp-tbl"></tbody>
                </table>
            </div>

            {{-- Light Table --}}
            <div class="panel overflow-x-auto mb-5">
                <h2 class="text-sm text-gray-500 mb-3 uppercase tracking-wider">10 Pembacaan Cahaya Terbaru</h2>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="table-th">#</th>
                            <th class="table-th">Lux</th>
                            <th class="table-th">Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="tbl"></tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-5 text-xs text-gray-700" id="footer">
            Auto-refresh 60 detik
        </div>
    </div>

    {{-- Auto-mode config modal --}}
    <div class="modal-overlay hidden" id="auto-modal">
        <div class="modal-content">
            <h3 class="text-sm font-bold text-gray-200 mb-4">Auto-Mode — <span id="modal-relay-name">Relay 1</span></h3>
            <input type="hidden" id="modal-relay-id">

            <label class="flex items-center gap-2 mb-4 cursor-pointer">
                <input type="checkbox" id="modal-auto-enabled" class="accent-forest">
                <span class="text-sm text-gray-300">Aktifkan auto-mode</span>
            </label>

            <div class="mb-3">
                <label class="text-xs text-gray-500 block mb-1">Nyalakan jika lux di bawah</label>
                <input type="number" id="modal-lux-on" class="modal-input" min="0" step="1">
            </div>

            <div class="mb-4">
                <label class="text-xs text-gray-500 block mb-1">Matikan jika lux di atas</label>
                <input type="number" id="modal-lux-off" class="modal-input" min="0" step="1">
            </div>

            <div class="text-xs text-red-400 mb-3 hidden" id="modal-error"></div>

            <div class="flex justify-end gap-2">
                <button class="modal-btn modal-btn-cancel" onclick="window.closeAutoModal()">Batal</button>
                <button class="modal-btn modal-btn-save" onclick="window.saveAutoConfig()">Simpan</button>
            </div>
        </div>
    </div>
</body>
</html>
