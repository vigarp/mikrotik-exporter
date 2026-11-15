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
      --hover-light: #f1f5f9;

      --bg-dark: #0f172a;
      --surface-dark: #1e293b;
      --border-dark: #334155;
      --text-dark: #f1f5f9;
      --muted-dark: #94a3b8;
      --accent-dark: #60a5fa;
      --hover-dark: #283548;

      --bg: var(--bg-light);
      --surface: var(--surface-light);
      --border: var(--border-light);
      --text: var(--text-light);
      --muted: var(--muted-light);
      --accent: var(--accent-light);
      --hover: var(--hover-light);

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
        --hover: var(--hover-dark);
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
        --hover: light-dark(var(--hover-light), var(--hover-dark));
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

    .shell {
      max-inline-size: 980px;
      margin-inline: auto;
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 1rem;
      align-items: start;
    }

    .sidebar {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.75rem;
      display: grid;
      gap: 0.5rem;
    }

    .brand {
      padding-inline: 0.5rem;
      padding-block: 0.25rem 0.5rem;
      border-block-end: 1px solid var(--border);
    }

    .brand h1 {
      margin: 0;
      font-size: 0.98rem;
      font-weight: 600;
    }

    .brand small {
      color: var(--muted);
      font-size: 0.76rem;
    }

    .tablist {
      display: grid;
      gap: 0.25rem;
    }

    [role="tab"] {
      position: relative;
      display: flex;
      justify-content: space-between;
      align-items: center;
      inline-size: 100%;
      text-align: start;
      font: inherit;
      font-size: 0.88rem;
      padding-block: 0.55rem;
      padding-inline: 0.75rem;
      border: 0;
      border-radius: 6px;
      background: transparent;
      color: var(--text);
      cursor: pointer;
    }

    [role="tab"]:hover {
      background: var(--hover);
    }

    [role="tab"][aria-selected="true"] {
      color: var(--accent);
      background: var(--hover);
    }

    [role="tab"][aria-selected="true"]::after {
      content: "";
      position: absolute;
      inset-block: 0.35rem;
      inset-inline-start: 0;
      border-inline-start: 3px solid var(--accent);
    }

    .tab-endpoint {
      font-size: 0.72rem;
      color: var(--muted);
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .main-col {
      min-inline-size: 0;
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
      padding-block: 0.8rem;
      padding-inline: 1.1rem;
    }

    .subtitle {
      font-size: 0.84rem;
      color: var(--muted);
      min-inline-size: 0;
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
      padding-block: 0.2rem;
      padding-inline: 0.6rem;
      border-radius: 999px;
      font-size: 0.76rem;
      font-weight: 600;
    }

    .badge.up { background: var(--ok-bg); color: var(--ok-fg); }
    .badge.down { background: var(--down-bg); color: var(--down-fg); }
    .badge.warn { background: var(--warn-bg); color: var(--warn-fg); }

    .btn, a.btn-link {
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

    .btn:hover, a.btn-link:hover {
      border-color: var(--accent);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: wait;
    }

    .alert {
      background: var(--down-bg);
      color: var(--down-fg);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 0.88rem;
      display: none;
    }

    .alert.visible {
      display: block;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding-block: 1rem;
      padding-inline: 1.2rem;
    }

    .card h2 {
      margin: 0 0 0.75rem 0;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--muted);
    }

    .kv-list {
      margin: 0;
      display: grid;
      gap: 0.5rem;
    }

    .kv {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 0.75rem;
      font-size: 0.9rem;
      border-block-end: 1px dashed var(--border);
      padding-block-end: 0.4rem;
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

    @media (max-width: 720px) {
      .shell {
        grid-template-columns: 1fr;
      }

      .tablist {
        display: flex;
        overflow-x: auto;
        gap: 0.35rem;
      }

      [role="tab"] {
        inline-size: auto;
        flex: none;
        gap: 0.4rem;
      }

      [role="tab"][aria-selected="true"]::after {
        inset: auto 0.5rem 0;
        border-inline-start: 0;
        border-block-end: 3px solid var(--accent);
      }
    }
  </style>
</head>
<body>
  <div class="shell">
    <aside class="sidebar">
      <div class="brand">
        <h1>MikroTik Monitor</h1>
        <small>Per-tab API query</small>
      </div>
      <div class="tablist" role="tablist" aria-label="Monitoring Sections" aria-orientation="vertical">
        <button type="button" role="tab" id="tab-connection" data-tab="connection" aria-selected="true">
          <span>PPPoE Connection</span>
          <span class="tab-endpoint">/connection</span>
        </button>
        <button type="button" role="tab" id="tab-usage" data-tab="usage" aria-selected="false">
          <span>Traffic &amp; Usage</span>
          <span class="tab-endpoint">/usage</span>
        </button>
        <button type="button" role="tab" id="tab-network" data-tab="network" aria-selected="false">
          <span>Network &amp; Gateway</span>
          <span class="tab-endpoint">/network</span>
        </button>
        <button type="button" role="tab" id="tab-router" data-tab="router" aria-selected="false">
          <span>Router System</span>
          <span class="tab-endpoint">/router</span>
        </button>
      </div>
    </aside>

    <main class="main-col">
      <header class="topbar">
        <div class="subtitle" id="meta-line">Memuat data...</div>
        <div class="actions">
          <span class="badge warn" id="status-badge">LOADING</span>
          <button type="button" class="btn" id="refresh-btn">Refresh</button>
          <a class="btn-link" id="api-link" href="/api/connection" target="_blank" rel="noopener">JSON</a>
        </div>
      </header>

      <div class="alert" id="error-box" role="status"></div>

      <section role="tabpanel" id="panel-connection" aria-labelledby="tab-connection" tabindex="0">
        <article class="card">
          <h2>PPPoE Connection</h2>
          <dl class="kv-list" id="conn-list"></dl>
        </article>
      </section>

      <section role="tabpanel" id="panel-usage" aria-labelledby="tab-usage" hidden>
        <article class="card">
          <h2>Traffic &amp; Data Usage</h2>
          <dl class="kv-list" id="usage-list"></dl>
        </article>
      </section>

      <section role="tabpanel" id="panel-network" aria-labelledby="tab-network" hidden>
        <article class="card">
          <h2>Network &amp; Gateway</h2>
          <dl class="kv-list" id="net-list"></dl>
        </article>
      </section>

      <section role="tabpanel" id="panel-router" aria-labelledby="tab-router" hidden>
        <article class="card">
          <h2>Router System</h2>
          <dl class="kv-list" id="sys-list"></dl>
        </article>
      </section>
    </main>
  </div>

  <script>
    const tabs = Array.from(document.querySelectorAll('[role="tab"]'));
    const panels = {
      connection: document.getElementById('panel-connection'),
      usage: document.getElementById('panel-usage'),
      network: document.getElementById('panel-network'),
      router: document.getElementById('panel-router'),
    };

    const statusBadge = document.getElementById('status-badge');
    const metaLine = document.getElementById('meta-line');
    const errorBox = document.getElementById('error-box');
    const refreshBtn = document.getElementById('refresh-btn');
    const apiLink = document.getElementById('api-link');

    let activeTab = 'connection';

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

    function renderTabData(tabName, data) {
      if (tabName === 'connection') {
        const hc = data.home_connection || {};
        const bw = hc.bandwidth_limit;
        const ql = hc.queue_limit?.max_limit;
        const sec = hc.secret_info || {};
        const lat = hc.latency;
        renderList('conn-list', [
          ['User', hc.user || data.user || '-'],
          ['IP Address', hc.ip_address || '-'],
          ['Uptime', hc.uptime || '-'],
          ['Caller ID (MAC)', hc.caller_id || sec.last_caller_id || '-'],
          ['Session ID / Auth', hc.session_id ? `${hc.session_id} (${hc.radius ? 'RADIUS' : 'Local'})` : '-'],
          ['Profile Limit', bw ? `${hc.profile_name || '-'} (${bw.max_upload_mbps}M / ${bw.max_download_mbps}M)` : (hc.profile_name || '-')],
          ['Queue Max Limit', ql ? `${ql.max_upload_mbps}M / ${ql.max_download_mbps}M` : '-'],
          ['Latency (Router→Client)', lat ? `${lat.avg_rtt_ms ?? '-'} ms (loss ${lat.packet_loss_percent ?? 0}%)` : '-'],
          ['Active Connections', hc.active_connections ?? '-'],
          ['Last Logout / Reason', sec.last_logged_out ? `${sec.last_logged_out} (${sec.last_disconnect_reason || '-'})` : '-'],
        ]);
      } else if (tabName === 'usage') {
        const hc = data.home_connection || {};
        const du = hc.data_usage;
        const lt = hc.live_traffic;
        const ih = hc.interface_health;
        renderList('usage-list', [
          ['User', hc.user || '-'],
          ['Live Upload', lt ? `${lt.upload_kbps} kbps` : '-'],
          ['Live Download', lt ? `${lt.download_kbps} kbps` : '-'],
          ['Session Download', du ? du.session_download_human : '-'],
          ['Session Upload', du ? du.session_upload_human : '-'],
          ['Session Total', du ? du.session_total_human : '-'],
          ['Interface / MTU', lt ? `${lt.interface_name} (MTU ${ih?.actual_mtu ?? '-'})` : '-'],
          ['Link Downs / Up Since', ih ? `${ih.link_downs}x (${ih.last_link_up_time || '-'})` : '-'],
          ['Drops (Rx/Tx) & Errors', ih ? `Drop ${ih.rx_drop}/${ih.tx_drop} · Err ${ih.rx_error}/${ih.tx_error}` : '-'],
        ]);
      } else if (tabName === 'network') {
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
          ['Allow Remote DNS', dns ? (dns.allow_remote ? 'Yes' : 'No') : '-'],
        ]);
      } else if (tabName === 'router') {
        const rs = data.network_health?.router_system;
        renderList('sys-list', [
          ['Board', rs?.board_name || '-'],
          ['RouterOS', rs?.version || '-'],
          ['CPU Load', rs ? `${rs.cpu_load_percent}%` : '-'],
          ['Free / Total RAM', rs ? `${rs.free_memory_mb} / ${rs.total_memory_mb} MB` : '-'],
          ['Router Uptime', rs?.uptime || '-'],
        ]);
      }
    }

    async function loadTab(tabName) {
      const endpoint = `/api/${tabName}${window.location.search}`;
      apiLink.href = endpoint;
      apiLink.textContent = `JSON /api/${tabName}`;
      refreshBtn.disabled = true;
      errorBox.classList.remove('visible');
      metaLine.textContent = `Mengambil ${endpoint}...`;

      try {
        const res = await fetch(endpoint, { cache: 'no-store' });
        const data = await res.json();

        const isUp = Boolean(data.online);
        const health = data.diagnostics?.overall_health || data.status || 'UNKNOWN';
        statusBadge.textContent = (data.status || (isUp ? 'UP' : 'DOWN')) + ' · ' + health;
        statusBadge.className = 'badge ' + (isUp ? 'up' : 'down');
        metaLine.textContent = `Endpoint: /api/${tabName} · Update: ${formatTime(data.timestamp)}`;

        if (!isUp && data.message) {
          errorBox.textContent = data.message;
          errorBox.classList.add('visible');
        }

        renderTabData(tabName, data);
      } catch (err) {
        statusBadge.textContent = 'ERROR';
        statusBadge.className = 'badge down';
        errorBox.textContent = `Gagal memuat /api/${tabName}: ${err.message}`;
        errorBox.classList.add('visible');
      } finally {
        refreshBtn.disabled = false;
      }
    }

    function selectTab(tabName) {
      activeTab = tabName;
      for (const tab of tabs) {
        const selected = tab.dataset.tab === tabName;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      }
      for (const [name, panel] of Object.entries(panels)) {
        const isActive = name === tabName;
        panel.hidden = !isActive;
        if (isActive) {
          panel.setAttribute('tabindex', '0');
        } else {
          panel.removeAttribute('tabindex');
        }
      }
      loadTab(tabName);
    }

    for (const tab of tabs) {
      tab.addEventListener('click', () => selectTab(tab.dataset.tab));
    }

    refreshBtn.addEventListener('click', () => loadTab(activeTab));
    selectTab(activeTab);
  </script>
</body>
</html>
