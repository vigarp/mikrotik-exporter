<?php
/**
 * Lightweight Web UI for MikroTik PPPoE & Infrastructure Healthcheck
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title>MikroTik Exporter</title>
  <style>
    :root {
      --bg-light: #f5f7fa;
      --surface-light: #ffffff;
      --border-light: #e2e8f0;
      --text-light: #0f172a;
      --muted-light: #64748b;
      --accent-light: #2563eb;

      --bg-dark: #0f172a;
      --surface-dark: #1e293b;
      --border-dark: #334155;
      --text-dark: #f1f5f9;
      --muted-dark: #94a3b8;
      --accent-dark: #60a5fa;

      --bg: var(--bg-light);
      --surface: var(--surface-light);
      --border: var(--border-light);
      --text: var(--text-light);
      --muted: var(--muted-light);
      --accent: var(--accent-light);

      --ok-bg: #dcfce7;
      --ok-fg: #166534;
      --down-bg: #fee2e2;
      --down-fg: #991b1b;
      --warn-bg: #fef3c7;
      --warn-fg: #92400e;

      color-scheme: light dark;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --bg: var(--bg-dark);
        --surface: var(--surface-dark);
        --border: var(--border-dark);
        --text: var(--text-dark);
        --muted: var(--muted-dark);
        --accent: var(--accent-dark);
        --ok-bg: #14532d;
        --ok-fg: #bbf7d0;
        --down-bg: #7f1d1d;
        --down-fg: #fecaca;
        --warn-bg: #78350f;
        --warn-fg: #fde68a;
      }
    }

    @supports (color: light-dark(white, black)) {
      :root {
        --bg: light-dark(var(--bg-light), var(--bg-dark));
        --surface: light-dark(var(--surface-light), var(--surface-dark));
        --border: light-dark(var(--border-light), var(--border-dark));
        --text: light-dark(var(--text-light), var(--text-dark));
        --muted: light-dark(var(--muted-light), var(--muted-dark));
        --accent: light-dark(var(--accent-light), var(--accent-dark));
      }
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding-block: 1.5rem;
      padding-inline: 1rem;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.5;
    }

    .container {
      max-inline-size: 980px;
      margin-inline: auto;
      display: grid;
      gap: 1rem;
    }

    .topbar {
      display: flex;
      flex-wrap: wrap;
      align-items: safe center;
      gap: 0.75rem;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding-block: 0.9rem;
      padding-inline: 1.1rem;
    }

    .title-group {
      min-inline-size: 0;
    }

    .title-group h1 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .subtitle {
      font-size: 0.82rem;
      color: var(--muted);
    }

    .actions {
      margin-inline-start: auto;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding-block: 0.2rem;
      padding-inline: 0.6rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 600;
    }

    .badge.up { background: var(--ok-bg); color: var(--ok-fg); }
    .badge.down { background: var(--down-bg); color: var(--down-fg); }
    .badge.warn { background: var(--warn-bg); color: var(--warn-fg); }

    button, a.btn-link {
      font: inherit;
      font-size: 0.82rem;
      padding-block: 0.35rem;
      padding-inline: 0.7rem;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--bg);
      color: var(--text);
      cursor: pointer;
      text-decoration: none;
    }

    button:hover, a.btn-link:hover {
      border-color: var(--accent);
    }

    button:disabled {
      opacity: 0.6;
      cursor: wait;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1rem;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding-block: 0.95rem;
      padding-inline: 1.1rem;
      min-inline-size: 0;
    }

    .card h2 {
      margin: 0 0 0.65rem 0;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--muted);
    }

    .kv-list {
      margin: 0;
      display: grid;
      gap: 0.45rem;
    }

    .kv {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 0.75rem;
      font-size: 0.9rem;
      border-block-end: 1px dashed var(--border);
      padding-block-end: 0.35rem;
    }

    .kv:last-child {
      border-block-end: none;
      padding-block-end: 0;
    }

    .kv dt {
      color: var(--muted);
      font-size: 0.84rem;
      flex-shrink: 0;
    }

    .kv dd {
      margin: 0;
      font-weight: 500;
      text-align: end;
      word-break: break-word;
      min-inline-size: 0;
      font-variant-numeric: tabular-nums;
    }

    .alert {
      background: var(--down-bg);
      color: var(--down-fg);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 0.9rem;
      display: none;
    }

    .alert.visible {
      display: block;
    }
  </style>
</head>
<body>
  <main class="container">
    <header class="topbar">
      <div class="title-group">
        <h1>MikroTik PPPoE Monitor</h1>
        <div class="subtitle" id="meta-line">Memuat data dari /api...</div>
      </div>
      <div class="actions">
        <span class="badge warn" id="status-badge">LOADING</span>
        <button type="button" id="refresh-btn">Refresh</button>
        <a class="btn-link" id="api-link" href="/api" target="_blank" rel="noopener">JSON /api</a>
      </div>
    </header>

    <div class="alert" id="error-box" role="status"></div>

    <section class="grid" id="cards-grid">
      <article class="card">
        <h2>PPPoE Connection</h2>
        <dl class="kv-list" id="conn-list"></dl>
      </article>

      <article class="card">
        <h2>Traffic &amp; Data Usage</h2>
        <dl class="kv-list" id="usage-list"></dl>
      </article>

      <article class="card">
        <h2>Network &amp; Gateway</h2>
        <dl class="kv-list" id="net-list"></dl>
      </article>

      <article class="card">
        <h2>Router System</h2>
        <dl class="kv-list" id="sys-list"></dl>
      </article>
    </section>
  </main>

  <script>
    const statusBadge = document.getElementById('status-badge');
    const metaLine = document.getElementById('meta-line');
    const errorBox = document.getElementById('error-box');
    const refreshBtn = document.getElementById('refresh-btn');
    const apiLink = document.getElementById('api-link');

    const apiUrl = '/api' + window.location.search;
    apiLink.href = apiUrl;

    function renderList(elId, rows) {
      const el = document.getElementById(elId);
      el.textContent = '';
      for (const [label, value] of rows) {
        const div = document.createElement('div');
        div.className = 'kv';
        const dt = document.createElement('dt');
        dt.textContent = label;
        const dd = document.createElement('dd');
        dd.textContent = value ?? '-';
        div.append(dt, dd);
        el.append(div);
      }
    }

    function formatTime(iso) {
      if (!iso) return '-';
      const d = new Date(iso);
      return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
    }

    async function loadStatus() {
      refreshBtn.disabled = true;
      errorBox.classList.remove('visible');

      try {
        const res = await fetch(apiUrl, { cache: 'no-store' });
        const data = await res.json();

        const isUp = Boolean(data.online);
        const health = data.diagnostics?.overall_health || data.status || 'UNKNOWN';
        statusBadge.textContent = (data.status || (isUp ? 'UP' : 'DOWN')) + ' · ' + health;
        statusBadge.className = 'badge ' + (isUp ? 'up' : 'down');

        metaLine.textContent = 'Update: ' + formatTime(data.timestamp);

        if (!isUp && data.message) {
          errorBox.textContent = data.message;
          errorBox.classList.add('visible');
        }

        const hc = data.home_connection || {};
        const bw = hc.bandwidth_limit;
        renderList('conn-list', [
          ['User', hc.user || data.user || '-'],
          ['IP Address', hc.ip_address || '-'],
          ['Uptime', hc.uptime || '-'],
          ['Caller ID (MAC)', hc.caller_id || '-'],
          ['Profile', hc.profile_name || '-'],
          ['Bandwidth Limit', bw ? `${bw.max_upload_mbps}M / ${bw.max_download_mbps}M (${bw.raw_limit})` : '-'],
        ]);

        const du = hc.data_usage;
        const lt = hc.live_traffic;
        renderList('usage-list', [
          ['Live Upload', lt ? `${lt.upload_kbps} kbps` : '-'],
          ['Live Download', lt ? `${lt.download_kbps} kbps` : '-'],
          ['Session Download', du ? du.session_download_human : '-'],
          ['Session Upload', du ? du.session_upload_human : '-'],
          ['Session Total', du ? du.session_total_human : '-'],
          ['Interface', lt ? lt.interface_name : '-'],
        ]);

        const nh = data.network_health || {};
        const gw = nh.isp_gateway;
        const dns = nh.dns_resolver;
        const intel = nh.ip_intelligence;
        renderList('net-list', [
          ['Public IP', nh.public_ip || '-'],
          ['ISP / ASN', intel ? `${intel.isp_name || '-'} (${intel.asn || '-'})` : 'Off (?enrich=true)'],
          ['Gateway Status', gw ? `${gw.status} (${gw.gateway_ip || '-'})` : '-'],
          ['Gateway Comment', gw?.comment || '-'],
          ['DNS Servers', dns?.servers?.length ? dns.servers.join(', ') : '-'],
        ]);

        const rs = nh.router_system;
        renderList('sys-list', [
          ['Board', rs?.board_name || '-'],
          ['RouterOS', rs?.version || '-'],
          ['CPU Load', rs ? `${rs.cpu_load_percent}%` : '-'],
          ['Free / Total RAM', rs ? `${rs.free_memory_mb} / ${rs.total_memory_mb} MB` : '-'],
          ['Router Uptime', rs?.uptime || '-'],
        ]);
      } catch (err) {
        statusBadge.textContent = 'ERROR';
        statusBadge.className = 'badge down';
        errorBox.textContent = 'Gagal memuat /api: ' + err.message;
        errorBox.classList.add('visible');
      } finally {
        refreshBtn.disabled = false;
      }
    }

    refreshBtn.addEventListener('click', loadStatus);
    loadStatus();
  </script>
</body>
</html>
