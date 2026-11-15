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

    .tablist > [role="tab"] {
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

    .tablist > [role="tab"]:hover {
      background: var(--hover);
    }

    .tablist > [role="tab"][aria-selected="true"] {
      color: var(--accent);
      background: var(--hover);
    }

    .tablist > [role="tab"][aria-selected="true"]::after {
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

    .subtabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      margin-bottom: 1rem;
    }

    .subtab {
      font: inherit;
      font-size: 0.82rem;
      font-weight: 500;
      padding: 0.42rem 0.85rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--surface);
      color: var(--muted);
      cursor: pointer;
    }

    .subtab[aria-selected="true"] {
      background: var(--accent-soft);
      border-color: var(--accent);
      color: var(--text);
      font-weight: 600;
    }

    .stack {
      display: grid;
      gap: 1rem;
    }

    [hidden] {
      display: none !important;
    }

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
        <button type="button" role="tab" id="tab-olt" data-tab="olt" aria-selected="false">
          <span>HSGQ OLT</span>
          <span class="tab-endpoint">/olt</span>
        </button>
        <button type="button" role="tab" id="tab-platforms" data-tab="platforms" aria-selected="false">
          <span>Top Platforms</span>
          <span class="tab-endpoint">/platforms</span>
        </button>
        <button type="button" role="tab" id="tab-insights" data-tab="insights" aria-selected="false">
          <span>RB5009 Insights</span>
          <span class="tab-endpoint">/insights</span>
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

      <section role="tabpanel" id="panel-olt" aria-labelledby="tab-olt" hidden>
        <div class="stack">
          <article class="card">
            <h2>OLT System &amp; PON Fleet</h2>
            <dl class="kv-list" id="olt-system-list"></dl>
          </article>
          <article class="card">
            <h2>HSGQ OLT PON Traffic</h2>
            <dl class="kv-list" id="olt-list"></dl>
          </article>
          <article class="card">
            <h2>All PON Ports (ONT Online / Offline)</h2>
            <dl class="kv-list" id="olt-ports-list"></dl>
          </article>
        </div>
      </section>

      <section role="tabpanel" id="panel-platforms" aria-labelledby="tab-platforms" hidden>
        <div class="stack">
          <article class="card">
            <h2>Global Top Platforms (Active Snapshot)</h2>
            <dl class="kv-list" id="platforms-summary-list"></dl>
          </article>
          <article class="card">
            <div class="subtabs" role="tablist" aria-label="Platform Sort Mode">
              <button type="button" class="subtab" role="tab" data-platform-mode="connections" aria-selected="true">By Connections</button>
              <button type="button" class="subtab" role="tab" data-platform-mode="bytes" aria-selected="false">By Active Bytes</button>
            </div>
            <h2 id="platforms-list-title">Top Platforms by Active Connections</h2>
            <dl class="kv-list" id="platforms-list"></dl>
          </article>
        </div>
      </section>

      <section role="tabpanel" id="panel-insights" aria-labelledby="tab-insights" hidden>
        <div class="subtabs" role="tablist" aria-label="RB5009 Insight Sections">
          <button type="button" class="subtab" role="tab" data-insight-section="fleet" aria-selected="true">Fleet &amp; Churn</button>
          <button type="button" class="subtab" role="tab" data-insight-section="top_users" aria-selected="false">Top Users &amp; Anomaly</button>
          <button type="button" class="subtab" role="tab" data-insight-section="ports" aria-selected="false">Ports &amp; SFP</button>
          <button type="button" class="subtab" role="tab" data-insight-section="routing" aria-selected="false">Routing &amp; Pools</button>
        </div>

        <div class="stack" data-insight-panel="fleet">
          <article class="card">
            <h2>PPPoE Fleet Summary</h2>
            <dl class="kv-list" id="ins-fleet-summary"></dl>
          </article>
          <article class="card">
            <h2>Recent Offline Users</h2>
            <dl class="kv-list" id="ins-fleet-offline"></dl>
          </article>
          <article class="card">
            <h2>Shortest Active Uptimes (Potential Flapping)</h2>
            <dl class="kv-list" id="ins-fleet-uptimes"></dl>
          </article>
        </div>

        <div class="stack" data-insight-panel="top_users" hidden>
          <article class="card">
            <h2>Top PPPoE Users by Session Traffic</h2>
            <dl class="kv-list" id="ins-users-bytes"></dl>
          </article>
          <article class="card">
            <h2>Top IPs / Users by Active Connections</h2>
            <dl class="kv-list" id="ins-users-conns"></dl>
          </article>
        </div>

        <div class="stack" data-insight-panel="ports" hidden>
          <article class="card">
            <h2>System Health &amp; SFP Optical Diagnostics</h2>
            <dl class="kv-list" id="ins-ports-health"></dl>
          </article>
          <article class="card">
            <h2>Physical Interfaces (Drops &amp; Errors)</h2>
            <dl class="kv-list" id="ins-ports-list"></dl>
          </article>
        </div>

        <div class="stack" data-insight-panel="routing" hidden>
          <article class="card">
            <h2>Default Gateways (0.0.0.0/0)</h2>
            <dl class="kv-list" id="ins-routing-gw"></dl>
          </article>
          <article class="card">
            <h2>IP Pool Utilization</h2>
            <dl class="kv-list" id="ins-routing-pools"></dl>
          </article>
        </div>
      </section>
    </main>
  </div>

  <script>
    const tabs = Array.from(document.querySelectorAll('.tablist > [role="tab"]'));
    const panels = {
      connection: document.getElementById('panel-connection'),
      usage: document.getElementById('panel-usage'),
      network: document.getElementById('panel-network'),
      router: document.getElementById('panel-router'),
      olt: document.getElementById('panel-olt'),
      platforms: document.getElementById('panel-platforms'),
      insights: document.getElementById('panel-insights'),
    };
    const platformSubtabs = Array.from(document.querySelectorAll('[data-platform-mode]'));
    const insightSubtabs = Array.from(document.querySelectorAll('[data-insight-section]'));
    const insightPanels = Array.from(document.querySelectorAll('[data-insight-panel]'));
    let activePlatformMode = 'connections';
    let activeInsightSection = 'fleet';
    let lastPlatformData = null;

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
      } else if (tabName === 'olt') {
        const sys = data.olt_system || {};
        const ponAll = data.olt_pon_all || {};
        renderList('olt-system-list', [
          ['Model / Vendor', sys.product_name ? `${sys.product_name} (${sys.vendor || 'HSGQ'})` : '-'],
          ['Firmware / Hardware', sys.fw_ver ? `${sys.fw_ver} (${sys.hw_ver || '-'})` : '-'],
          ['CPU / Memory Usage', sys.cpu_usage_percent !== undefined ? `${sys.cpu_usage_percent}% / ${sys.memory_usage_percent ?? '-'}%` : '-'],
          ['Total ONTs (Online / Offline)', ponAll.total_ont !== undefined ? `${ponAll.total_online} online / ${ponAll.total_offline} offline (Total ${ponAll.total_ont})` : '-'],
        ]);

        const op = data.olt_pon || {};
        const latest = op.latest || {};
        const sum = op.summary || {};
        renderList('olt-list', [
          ['Port', op.port_name ? `${op.port_name} (ID ${op.port_id})` : '-'],
          ['Port Status', op.status || '-'],
          ['Latest Sample', latest.monitor_time || '-'],
          ['Latest TX (Download)', latest.tx_mbps !== undefined ? `${latest.tx_mbps} Mbps` : '-'],
          ['Latest RX (Upload)', latest.rx_mbps !== undefined ? `${latest.rx_mbps} Mbps` : '-'],
          ['Average (TX / RX)', sum.avg_tx_mbps !== undefined ? `${sum.avg_tx_mbps} / ${sum.avg_rx_mbps} Mbps` : '-'],
          ['Peak (TX / RX)', sum.peak_tx_mbps !== undefined ? `${sum.peak_tx_mbps} / ${sum.peak_rx_mbps} Mbps` : '-'],
          ['Min (TX / RX)', sum.min_tx_mbps !== undefined ? `${sum.min_tx_mbps} / ${sum.min_rx_mbps} Mbps` : '-'],
          ['Monitor Window', op.cur_count ? `${op.cur_count}/${op.max_count} (${op.interval_seconds}s interval, ${sum.valid_samples ?? 0} valid)` : '-'],
          ['Errors (RX / TX)', sum.rx_error_frames !== undefined ? `${sum.rx_error_frames} / ${sum.tx_error_frames}` : '-'],
          ['Discards (RX / TX)', sum.rx_discard_frames !== undefined ? `${sum.rx_discard_frames} / ${sum.tx_discard_frames}` : '-'],
        ]);

        const portRows = (ponAll.ports || []).map((p) => [
          `${p.port_name} (${p.link_status} / ${p.admin_status})`,
          `${p.online} online · ${p.offline} offline`
        ]);
        renderList('olt-ports-list', portRows.length ? portRows : [['Status', 'No PON summary available']]);
      } else if (tabName === 'platforms') {
        lastPlatformData = data;
        const pl = data.platforms || {};
        const sum = pl.summary || {};
        const listData = activePlatformMode === 'bytes'
          ? (pl.top_platforms_by_bytes || pl.top_platforms || [])
          : (pl.top_platforms || []);
        document.getElementById('platforms-list-title').textContent = activePlatformMode === 'bytes'
          ? 'Top Platforms by Active Bytes'
          : 'Top Platforms by Active Connections';
        const rows = listData.map((item, idx) => {
          const doms = item.sample_domains?.length ? ` · ${item.sample_domains.join(', ')}` : '';
          return [
            `#${idx + 1} ${item.platform} (${item.asn})`,
            activePlatformMode === 'bytes'
              ? `${item.total_human} (${item.byte_share_percent ?? 0}%) · ${item.connections} conn${doms}`
              : `${item.connections} conn (${item.connection_share_percent}%) · ${item.total_human}${doms}`
          ];
        });
        renderList('platforms-summary-list', [
          ['Active Connections', sum.total_connections ?? '-'],
          ['Public Connections', sum.public_connections ?? '-'],
          ['Unique Public IPs / /24', sum.unique_public_ips !== undefined ? `${sum.unique_public_ips} IPs / ${sum.unique_prefixes_24} /24` : '-'],
          ['Top-100 /24 Coverage', sum.coverage_percent !== undefined ? `${sum.coverage_percent}% (${sum.covered_connections} conn)` : '-'],
          ['Total Active Traffic', sum.total_active_traffic_human || '-'],
        ]);
        renderList('platforms-list', rows.length ? rows : [['Status', 'No active platform data']]);
      } else if (tabName === 'insights') {
        const ins = data.insights || {};
        if (ins.fleet) {
          const fl = ins.fleet;
          const sum = fl.summary || {};
          const reasons = Object.entries(sum.disconnect_reasons || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '-';
          renderList('ins-fleet-summary', [
            ['Total PPPoE Secrets', sum.total_secrets ?? '-'],
            ['Enabled / Disabled', sum.enabled_secrets !== undefined ? `${sum.enabled_secrets} / ${sum.disabled_secrets}` : '-'],
            ['Online / Offline', sum.online_count !== undefined ? `${sum.online_count} (${sum.online_percent}%) / ${sum.offline_count}` : '-'],
            ['Offline Disconnect Reasons', reasons],
          ]);
          const offRows = (fl.recent_offline || []).map((u) => [
            `${u.user} (${u.profile})`,
            `${u.last_logged_out} · ${u.last_disconnect_reason}`
          ]);
          renderList('ins-fleet-offline', offRows.length ? offRows : [['Status', 'Semua secret enabled sedang online']]);
          const upRows = (fl.shortest_uptimes || []).map((u, i) => [
            `#${i + 1} ${u.user}`,
            `${u.uptime} (${u.ip_address || '-'})`
          ]);
          renderList('ins-fleet-uptimes', upRows.length ? upRows : [['Status', 'No active users']]);
        }
        if (ins.top_users) {
          const tu = ins.top_users;
          const byteRows = (tu.top_by_session_bytes || []).map((u, i) => [
            `#${i + 1} ${u.user}`,
            `${u.total_human} (DL ${u.download_human} / UL ${u.upload_human}) · ${u.uptime}`
          ]);
          renderList('ins-users-bytes', byteRows.length ? byteRows : [['Status', 'No PPPoE interface counters']]);
          const connRows = (tu.top_by_connections || []).map((u, i) => [
            `#${i + 1} ${u.user || u.ip_address}`,
            `${u.connections} conn · ${u.active_traffic_human} (${u.ip_address})`
          ]);
          renderList('ins-users-conns', connRows.length ? connRows : [['Status', 'No active connections']]);
        }
        if (ins.ports) {
          const pt = ins.ports;
          const healthRows = (pt.health || []).map((h) => [h.name, `${h.value}${h.unit ? ' ' + h.unit : ''}`]);
          if (pt.sfp) {
            const s = pt.sfp;
            healthRows.push(
              [`${s.name} Link`, `${s.status} (${s.rate})`],
              [`${s.name} Optical (RX / TX)`, `${s.rx_power_dbm ?? '-'} dBm / ${s.tx_power_dbm ?? '-'} dBm`],
              [`${s.name} Temp / Voltage`, `${s.temperature_c ?? '-'} C / ${s.voltage_v ?? '-'} V (${s.wavelength_nm ?? '-'} nm)`]
            );
          }
          renderList('ins-ports-health', healthRows.length ? healthRows : [['Status', 'No health data']]);
          const portRows = (pt.physical_ports || []).map((p) => [
            `${p.name} (${p.disabled ? 'Disabled' : (p.running ? 'UP' : 'DOWN')})`,
            `RX ${p.rx_human} / TX ${p.tx_human} · Drop ${p.rx_drop}/${p.tx_drop} · Err ${p.rx_error}/${p.tx_error} · Flaps ${p.link_downs}x`
          ]);
          renderList('ins-ports-list', portRows.length ? portRows : [['Status', 'No physical ports']]);
        }
        if (ins.routing) {
          const rt = ins.routing;
          const gwRows = (rt.default_routes || []).map((r, i) => [
            `#${i + 1} ${r.comment || r.gateway} (dist ${r.distance})`,
            `${r.disabled ? 'DISABLED' : (r.active ? 'ACTIVE' : 'BACKUP')} · ${r.immediate_gw} [${r.routing_table}]`
          ]);
          renderList('ins-routing-gw', gwRows.length ? gwRows : [['Status', 'No default routes']]);
          const poolRows = (rt.ip_pools || []).map((p) => [
            `${p.name} (${p.ranges})`,
            `${p.used}/${p.total} used (${p.used_percent}%) · ${p.available} free`
          ]);
          renderList('ins-routing-pools', poolRows.length ? poolRows : [['Status', 'No IP pools']]);
        }
      }
    }

    async function loadTab(tabName) {
      let query = window.location.search;
      if (tabName === 'insights') {
        const params = new URLSearchParams(window.location.search);
        params.set('section', activeInsightSection);
        query = `?${params.toString()}`;
      }
      const endpoint = `/api/${tabName}${query}`;
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

    for (const st of platformSubtabs) {
      st.addEventListener('click', () => {
        activePlatformMode = st.dataset.platformMode;
        for (const b of platformSubtabs) {
          b.setAttribute('aria-selected', b === st ? 'true' : 'false');
        }
        if (lastPlatformData) {
          renderTabData('platforms', lastPlatformData);
        }
      });
    }

    for (const st of insightSubtabs) {
      st.addEventListener('click', () => {
        activeInsightSection = st.dataset.insightSection;
        for (const b of insightSubtabs) {
          b.setAttribute('aria-selected', b === st ? 'true' : 'false');
        }
        for (const p of insightPanels) {
          p.hidden = p.dataset.insightPanel !== activeInsightSection;
        }
        loadTab('insights');
      });
    }

    refreshBtn.addEventListener('click', () => loadTab(activeTab));
    selectTab(activeTab);
  </script>
</body>
</html>
