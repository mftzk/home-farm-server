# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IoT dashboard for monitoring light levels, temperature/humidity sensors and controlling relays connected to ESP8266/ESP32 microcontrollers. Built with Laravel 13 + Vite + Tailwind CSS + Chart.js. Data is stored in MariaDB. The UI is in Indonesian.

## Common Commands

```bash
# Development setup
composer setup          # Install deps, generate key, run migrations, build assets

# Run development server (starts web server, queue worker, log viewer, Vite HMR)
composer run dev

# Run tests
composer run test       # Clears config cache then runs PHPUnit

# Build frontend assets
npm run build
npm run dev             # Vite dev server with HMR

# Production (Docker)
docker-compose up -d    # Starts web (PHP 8.4/Apache) + MariaDB 11
```

## Architecture

**Backend:** Laravel with three scheduled console commands (run every minute via `routes/console.php`):
- `FetchLightData` – pulls lux from ESP8266 sensor
- `FetchTemperatureData` – pulls temp/humidity from SHT40 sensor
- `EvaluateAutoRelay` – toggles relays based on sensor thresholds

**Frontend:** Single Blade view (`resources/views/dashboard.blade.php`) with all JS logic in `resources/js/app.js`. Three dashboard tabs: light monitor, temperature/humidity, and relay control. Charts use Chart.js with time-range filtering (1h–30d).

**API routes** (in `routes/api.php`):
- `/api/readings` – light sensor data with range/stats filtering
- `/api/temperature` – temperature/humidity data
- `/api/relay/*` – relay status, toggle, bulk control, auto-config

**Models:** `LightReading`, `TemperatureReading`, `RelayAutoConfig`

**ESP device IPs** are configured via environment variables (`ESP_IP`, `ESP_RELAY_IP`, `ESP_TEMP_IP`) loaded through `config/esp.php`.

## Docker

Multi-stage Dockerfile: Node 20 builds frontend assets → Composer installs PHP deps → PHP 8.4 Apache final image. Uses `host` network mode to access LAN ESP devices. `entrypoint.sh` runs migrations and starts cron + Apache.

## Database

MariaDB with three main tables: `light_readings` (lux, recorded_at), `temperature_readings` (temperature, humidity, recorded_at), `relay_auto_configs` (per-relay automation settings with thresholds). Migrations are idempotent.
