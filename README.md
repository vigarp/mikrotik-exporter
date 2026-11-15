# 🚀 MikroTik PPPoE & Infrastructure Healthcheck Exporter for Uptime Kuma

A lightweight, zero-dependency RESTful API Wrapper designed to monitor MikroTik RouterOS PPPoE user connections, ISP gateway reachability, public IP/ASN intelligence, and router hardware health metrics. Built specifically for seamless integration with **Uptime Kuma** using Docker.

---

## 🌟 Key Features

- **Single Unified Endpoint**: Clean JSON response providing all vital network metrics in a single API request.
- **PPPoE Session, Traffic & Data Usage**: Tracks active connection status, IP address, uptime, MAC address, bandwidth limits (Mbps), live upload/download bitrate (Kbps/bps), and total session data consumption (MB/GB).
- **ISP Gateway Monitoring**: Tracks default route (`0.0.0.0/0`) reachability to verify if the ISP WAN uplink is UP or DOWN.
- **Router Hardware Health**: Monitors CPU load (%), RAM usage (MB), RouterOS version, board name, and system uptime.
- **Public IP & DNS Resolver Info**: Detects public WAN IP and configured DNS resolver servers.
- **On-Demand IP Intelligence**: Supports optional Cloudflare Radar API / IP-API enrichment via `?enrich=true` to prevent unnecessary external API load during automated Uptime Kuma polling.
- **Uptime Kuma Ready**: Returns `200 OK` when the target PPPoE session is online, and `503 Service Unavailable` when offline or disconnected.

---

## ⚙️ Environment Variables Configuration (`.env`)

Copy `.env.example` to `.env` and fill in your actual credentials:

```bash
cp .env.example .env
```

Example `.env` structure:

```env
# MikroTik RouterOS Connection Configuration
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=admin
MIKROTIK_PASS=your_strong_password
MIKROTIK_PPPOE_USER=your_pppoe_username
MIKROTIK_TIMEOUT=5

# Optional: Cloudflare Radar API Token, Intel, and Console Logging
CLOUDFLARE_RADAR_TOKEN=your_cloudflare_radar_api_token
ENABLE_EXTERNAL_INTEL=false
ENABLE_LOG=true
```

> ⚠️ **Security Warning**: Never commit your `.env` file to Git! Ensure `.env` is listed in your `.gitignore`.

---

## 📡 API Usage & Query Parameters

### Endpoints:
- **`/`** — Lightweight Web UI displaying live status, PPPoE session, data usage, ISP gateway, and router health.
- **`/api`** — JSON healthcheck endpoint for Uptime Kuma and `curl` (supports `?user=...` and `?enrich=true`).

### Base URL:
- **Docker Production**: `http://localhost:8080/api` or `http://mikrotik_rest_api:8080/api`
- **Apache Local**: `http://localhost/mikrotik-exporter/api`

### Query Parameters:
| Parameter | Default | Description |
| :--- | :--- | :--- |
| `user` | `.env` value | Override the target PPPoE user to monitor. |
| `enrich` | `false` | Set to `true` (e.g. `?enrich=true`) to fetch ASN, ISP name, and Cloudflare Radar intelligence. |

---

## 📊 Sample Response JSON

### 🟢 `200 OK` (User Online - Uptime Kuma UP):
```json
{
    "status": "UP",
    "online": true,
    "timestamp": "2026-08-25T15:00:00+07:00",
    "diagnostics": {
        "router_connected": true,
        "router_status": "ONLINE",
        "user_pppoe_status": "ONLINE",
        "isp_gateway_status": "REACHABLE",
        "overall_health": "ALL_SYSTEMS_OPERATIONAL"
    },
    "home_connection": {
        "user": "your_pppoe_user",
        "ip_address": "10.0.0.100",
        "uptime": "1w2d3h45m",
        "caller_id": "00:11:22:33:44:55",
        "profile_name": "50Mbps-Profile",
        "bandwidth_limit": {
            "max_upload_mbps": 50,
            "max_download_mbps": 50,
            "raw_limit": "50M/50M"
        },
        "data_usage": {
            "session_download_mb": 14250.75,
            "session_upload_mb": 1820.30,
            "session_total_mb": 16071.05,
            "session_total_human": "15.70 GB",
            "session_download_human": "13.92 GB",
            "session_upload_human": "1.78 GB",
            "tx_bytes": 14942767104,
            "rx_bytes": 1908734976,
            "total_bytes": 16851502080
        },
        "live_traffic": {
            "interface_name": "<pppoe-your_pppoe_user>",
            "upload_kbps": 51.95,
            "download_kbps": 34.41,
            "tx_bps": 51952,
            "rx_bps": 34408
        }
    },
    "network_health": {
        "public_ip": "203.0.113.1",
        "ip_intelligence": null,
        "dns_resolver": {
            "servers": ["1.1.1.1", "8.8.8.8"],
            "static": "1.1.1.1,8.8.8.8",
            "dynamic": "192.168.1.1",
            "allow_remote": true
        },
        "isp_gateway": {
            "gateway_ip": "192.168.1.1",
            "active": true,
            "status": "REACHABLE",
            "distance": 1,
            "comment": "GW-MAIN-ISP"
        },
        "router_system": {
            "cpu_load_percent": 15,
            "free_memory_mb": 128.5,
            "total_memory_mb": 256.0,
            "uptime": "3w2d1h",
            "board_name": "MikroTik",
            "version": "7.x (stable)"
        }
    }
}
```

### 🔴 `503 Service Unavailable` (Router Unreachable - Uptime Kuma DOWN):
*(Note: If the router is reachable but the PPPoE user is offline, `503` is also returned with `diagnostics.overall_health = "USER_DISCONNECTED"` along with `home_connection` and `network_health` payloads.)*
```json
{
    "status": "DOWN",
    "online": false,
    "user": "your_pppoe_user",
    "timestamp": "2026-08-25T15:00:00+07:00",
    "diagnostics": {
        "router_connected": false,
        "router_status": "OFFLINE",
        "user_pppoe_status": "UNKNOWN",
        "isp_gateway_status": "UNKNOWN",
        "overall_health": "ROUTER_UNREACHABLE"
    },
    "message": "Unable to connect to RouterOS API"
}
```

---

## 🐋 Docker Deployment Guide

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/mikrotik-exporter.git
   cd mikrotik-exporter
   ```
2. Copy environment file & fill credentials:
   ```bash
   cp .env.example .env
   nano .env
   ```
3. Build and launch the container in background mode:
   ```bash
   docker compose up -d --build
   ```
4. Test the healthcheck endpoint:
   ```bash
   curl http://localhost:8080/api
   ```

---

## 🔔 Uptime Kuma Setup Guide

1. Open **Uptime Kuma** Dashboard.
2. Click **Add New Monitor**.
3. Configure the fields:
   - **Monitor Type**: `HTTP(s)`
   - **Friendly Name**: `Home Internet (PPPoE)`
   - **URL**: `http://mikrotik_rest_api:8080/api` *(if on same Docker network)* or `http://<YOUR_VPS_IP>:8080/api`
   - **Accepted Status Codes**: `200`
   - **Heartbeat Interval**: `180` seconds (3 minutes)
4. *(Optional Advanced Monitor)*:
   - Create a second monitor with type `HTTP(s) - Json Query`.
   - **Json Path**: `$.network_health.isp_gateway.status`
   - **Expected Value**: `REACHABLE`
   - **Friendly Name**: `ISP WAN Uplink (Telkom)`

---

## 🛡️ License & Ethics

Designed as a low-profile, zero-impact personal healthcheck exporter for local RT/RW Net infrastructure monitoring. Please poll responsibly (recommended interval: 3+ minutes) to ensure zero performance degradation on provider hardware.
