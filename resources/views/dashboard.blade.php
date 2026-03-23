<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Light Monitor Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-dark-900 text-gray-100 min-h-screen font-sans">
    <div class="max-w-[960px] mx-auto px-4 py-6">

        <h1 class="text-center text-2xl font-bold text-gold mb-1">
            &#9728; Light Monitor Dashboard
        </h1>
        <p class="text-center text-sm text-gray-500 mb-5">
            ESP8266 + BH1750 &mdash; Time Series
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
                <div class="card-value text-gold" id="c-cur">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Min</div>
                <div class="card-value text-blue-400" id="c-min">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Max</div>
                <div class="card-value text-orange-400" id="c-max">--</div>
                <div class="card-unit">lux</div>
            </div>
            <div class="card">
                <div class="card-label">Rata-rata</div>
                <div class="card-value text-emerald-400" id="c-avg">--</div>
                <div class="card-unit">lux</div>
            </div>
        </div>

        {{-- Chart --}}
        <div class="panel mb-5">
            <canvas id="chart"></canvas>
        </div>

        {{-- Table --}}
        <div class="panel overflow-x-auto">
            <h2 class="text-sm text-gray-400 mb-3">10 Pembacaan Terbaru</h2>
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

        <div class="text-center mt-5 text-xs text-gray-600" id="footer">
            Auto-refresh 60 detik
        </div>
    </div>
</body>
</html>
