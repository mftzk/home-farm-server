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
            ESP8266 &mdash; Light Monitor + Relay Control
        </p>

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
                        <div class="card-label">Relay {{ $i + 1 }}</div>
                        <label class="relay-switch">
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

        {{-- Table --}}
        <div class="panel overflow-x-auto mb-5">
            <h2 class="text-sm text-gray-500 mb-3 uppercase tracking-wider">10 Pembacaan Terbaru</h2>
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

        <div class="text-center mt-5 text-xs text-gray-700" id="footer">
            Auto-refresh 60 detik
        </div>
    </div>
</body>
</html>
