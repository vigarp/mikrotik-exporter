<?php
/**
 * MikroTik PPPoE & Infrastructure Healthcheck Exporter for Uptime Kuma
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = rtrim($requestPath, '/') ?: '/';

// Serve lightweight Web UI on "/" (and "/index.php")
if ($normalizedPath === '/' || $normalizedPath === '/index.php') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/dashboard.php';
    exit;
}

$apiRoutes = [
    '/api'            => 'all',
    '/api/connection' => 'connection',
    '/api/usage'      => 'usage',
    '/api/network'    => 'network',
    '/api/router'     => 'router',
    '/api/olt'        => 'olt',
    '/api/platforms'  => 'platforms',
];

if (!isset($apiRoutes[$normalizedPath])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'status'  => 'ERROR',
        'message' => 'Not Found. Use / for Web UI or /api, /api/connection, /api/usage, /api/network, /api/router, /api/olt, /api/platforms.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$routeScope = $apiRoutes[$normalizedPath];

require_once(__DIR__ . '/routeros_rest.class.php');

// Load .env file if running locally outside Docker
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Configuration from environment variables
$restUrl     = trim(getenv('MIKROTIK_REST_URL') ?: ($_ENV['MIKROTIK_REST_URL'] ?? ($_SERVER['MIKROTIK_REST_URL'] ?? 'http://192.168.88.1/rest')));
$proxy       = trim(getenv('MIKROTIK_PROXY') ?: ($_ENV['MIKROTIK_PROXY'] ?? ($_SERVER['MIKROTIK_PROXY'] ?? '')));
$username    = getenv('MIKROTIK_USER') ?: ($_ENV['MIKROTIK_USER'] ?? ($_SERVER['MIKROTIK_USER'] ?? 'admin'));
$password    = getenv('MIKROTIK_PASS') ?: ($_ENV['MIKROTIK_PASS'] ?? ($_SERVER['MIKROTIK_PASS'] ?? ''));
$pppoeUser   = trim($_GET['user'] ?? (getenv('MIKROTIK_PPPOE_USER') ?: ($_ENV['MIKROTIK_PPPOE_USER'] ?? ($_SERVER['MIKROTIK_PPPOE_USER'] ?? ''))));
$cfToken     = getenv('CLOUDFLARE_RADAR_TOKEN') ?: ($_ENV['CLOUDFLARE_RADAR_TOKEN'] ?? ($_SERVER['CLOUDFLARE_RADAR_TOKEN'] ?? null));
$enableIntel = (isset($_GET['enrich']) && ($_GET['enrich'] === 'true' || $_GET['enrich'] === '1'))
             || (isset($_GET['intel']) && ($_GET['intel'] === 'true' || $_GET['intel'] === '1'))
             || (getenv('ENABLE_EXTERNAL_INTEL') === 'true' || ($_ENV['ENABLE_EXTERNAL_INTEL'] ?? '') === 'true');
$timeout     = (int)(getenv('MIKROTIK_TIMEOUT') ?: ($_ENV['MIKROTIK_TIMEOUT'] ?? ($_SERVER['MIKROTIK_TIMEOUT'] ?? 5)));
$enableLog   = getenv('ENABLE_LOG') !== false
             ? (filter_var(getenv('ENABLE_LOG') ?: ($_ENV['ENABLE_LOG'] ?? true), FILTER_VALIDATE_BOOLEAN))
             : true;
$oltBaseUrl  = rtrim(trim(getenv('OLT_BASE_URL') ?: ($_ENV['OLT_BASE_URL'] ?? ($_SERVER['OLT_BASE_URL'] ?? ''))), '/');
$oltToken    = trim(getenv('OLT_TOKEN') ?: ($_ENV['OLT_TOKEN'] ?? ($_SERVER['OLT_TOKEN'] ?? '')));
$oltPortId   = (int)($_GET['port_id'] ?? (getenv('OLT_PORT_ID') ?: ($_ENV['OLT_PORT_ID'] ?? ($_SERVER['OLT_PORT_ID'] ?? 1))));

header('Content-Type: application/json; charset=utf-8');

function respond($status_code, $data) {
    global $enableLog;
    http_response_code($status_code);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($enableLog) {
        $reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $reqUri    = $_SERVER['REQUEST_URI'] ?? '/';
        $clientIp  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timestamp = date('D M d H:i:s Y');
        $logHeader = sprintf("[%s] [%s] %s %s -> HTTP %d", $timestamp, $clientIp, $reqMethod, $reqUri, $status_code);
        @file_put_contents('php://stdout', $logHeader . "\n" . $json . "\n\n");
    }

    echo $json;
    exit;
}

function parseRateLimit($rateStr) {
    if (!$rateStr) return null;
    $baseRate = explode(' ', trim($rateStr))[0];
    $parts = explode('/', $baseRate);
    $rx = $parts[0] ?? null;
    $tx = $parts[1] ?? null;

    $convert = function($val) {
        if (!$val) return null;
        $val = strtoupper(trim($val));
        if (strpos($val, 'M') !== false) {
            return (float)str_replace('M', '', $val);
        }
        if (strpos($val, 'K') !== false) {
            return round((float)str_replace('K', '', $val) / 1024, 2);
        }
        if (is_numeric($val)) {
            return round((float)$val / 1048576, 2);
        }
        return $val;
    };

    return [
        'max_upload_mbps'   => $convert($rx),
        'max_download_mbps' => $convert($tx),
        'raw_limit'         => $rateStr
    ];
}

function formatBytes($bytes, $precision = 2) {
    if (!$bytes || $bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow = min((int)floor(log($bytes, 1024)), count($units) - 1);
    if ($pow < 0) $pow = 0;
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function parseRouterOsRttMs($rttStr) {
    if (!$rttStr || !is_string($rttStr)) return null;
    $ms = 0.0;
    if (preg_match('/(\d+)s/', $rttStr, $m)) {
        $ms += (int)$m[1] * 1000;
    }
    if (preg_match('/(\d+)ms/', $rttStr, $m)) {
        $ms += (int)$m[1];
    }
    if (preg_match('/(\d+)us/', $rttStr, $m)) {
        $ms += (int)$m[1] / 1000;
    }
    return round($ms, 2);
}

function getIpIntelligence($publicIp, $cfToken = null) {
    if (!$publicIp) return null;

    $info = [
        'ip'        => $publicIp,
        'provider'  => 'IP-API',
        'asn'       => null,
        'isp_name'  => null,
        'org'       => null,
        'country'   => null,
        'region'    => null,
        'city'      => null
    ];

    if (!empty($cfToken) && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/radar/entities/asns/ip?ip=" . urlencode($publicIp));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$cfToken}",
            "Content-Type: application/json"
        ]);
        $cfResp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $cfResp) {
            $json = json_decode($cfResp, true);
            if (!empty($json['success']) && isset($json['result']['asn'])) {
                $asnData = $json['result']['asn'];
                $info['provider'] = 'Cloudflare Radar API';
                $info['asn'] = 'AS' . ($asnData['asn'] ?? '');
                $info['isp_name'] = $asnData['name'] ?? null;
                $info['org'] = $asnData['aka'] ?? ($asnData['name'] ?? null);
                $info['country'] = $asnData['countryName'] ?? ($asnData['country'] ?? null);
                return $info;
            }
        }
    }

    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $apiUrl = "http://ip-api.com/json/" . urlencode($publicIp) . "?fields=status,country,regionName,city,isp,org,as";
    $jsonRaw = @file_get_contents($apiUrl, false, $context);
    if ($jsonRaw) {
        $json = json_decode($jsonRaw, true);
        if (isset($json['status']) && $json['status'] === 'success') {
            $info['asn']      = $json['as'] ?? null;
            $info['isp_name'] = $json['isp'] ?? null;
            $info['org']      = $json['org'] ?? null;
            $info['country']  = $json['country'] ?? null;
            $info['region']   = $json['regionName'] ?? null;
            $info['city']     = $json['city'] ?? null;
        }
    }

    return $info;
}

function rosGet($API, $path, array $query = []) {
    return $API->get($path, $query);
}

function rosAction($API, $path, array $payload = []) {
    return $API->post($path, $payload);
}

function rosCountFirewallConn($API, $clientIp) {
    $res = $API->post('/ip/firewall/connection/print', [
        'count-only' => '',
        '.query'     => ['src-address=' . $clientIp]
    ]);
    return is_array($res) && isset($res['ret']) && is_numeric($res['ret']) ? (int)$res['ret'] : null;
}

function resolvePppoeInterface($API, $pppoeUser, $withMonitorTraffic = false) {
    $candidates = [
        '<pppoe-' . $pppoeUser . '>',
        'pppoe-' . $pppoeUser,
        '<' . $pppoeUser . '>',
        $pppoeUser
    ];

    foreach ($candidates as $ifaceName) {
        if ($withMonitorTraffic) {
            $traffic = rosAction($API, '/interface/monitor-traffic', [
                'interface' => $ifaceName,
                'once'      => ''
            ]);

            if (!empty($traffic) && !isset($traffic['!trap'])) {
                $txBps = (int)($traffic[0]['tx-bits-per-second'] ?? 0);
                $rxBps = (int)($traffic[0]['rx-bits-per-second'] ?? 0);
                return [
                    'matched_iface' => $ifaceName,
                    'live_traffic'  => [
                        'interface_name' => $ifaceName,
                        'upload_kbps'    => round($rxBps / 1000, 2),
                        'download_kbps'  => round($txBps / 1000, 2),
                        'tx_bps'         => $txBps,
                        'rx_bps'         => $rxBps
                    ]
                ];
            }
        } else {
            $res = rosGet($API, '/interface', ['name' => $ifaceName]);
            if (!empty($res) && isset($res[0])) {
                return ['matched_iface' => $ifaceName, 'iface_row' => $res[0]];
            }
        }
    }

    return ['matched_iface' => null, 'live_traffic' => null, 'iface_row' => null];
}

function fetchPppoeActive($API, $pppoeUser) {
    return rosGet($API, '/ppp/active', ['name' => $pppoeUser]);
}

function fetchPppoeSecretAndLimit($API, $pppoeUser) {
    $secret = rosGet($API, '/ppp/secret', ['name' => $pppoeUser]);

    $profileName = $secret[0]['profile'] ?? null;
    $rateLimit = null;

    if (!empty($profileName)) {
        $profile = rosGet($API, '/ppp/profile', ['name' => $profileName]);
        $rateLimitStr = $profile[0]['rate-limit'] ?? ($secret[0]['rate-limit'] ?? null);
        $rateLimit = parseRateLimit($rateLimitStr);
    }

    return [
        'profile_name'    => $profileName,
        'bandwidth_limit' => $rateLimit,
        'secret_info'     => [
            'disabled'               => isset($secret[0]['disabled']) ? ($secret[0]['disabled'] === 'true') : null,
            'last_logged_out'        => $secret[0]['last-logged-out'] ?? null,
            'last_disconnect_reason' => $secret[0]['last-disconnect-reason'] ?? null,
            'last_caller_id'         => $secret[0]['last-caller-id'] ?? null
        ]
    ];
}

function fetchConnectionExtras($API, $matchedIface, $clientIp) {
    $queueLimit = null;
    $activeConnections = null;
    $latency = null;

    if ($matchedIface) {
        $queues = rosGet($API, '/queue/simple', ['target' => $matchedIface]);
        if (!empty($queues) && isset($queues[0])) {
            $q = $queues[0];
            $queueLimit = [
                'limit_at'       => parseRateLimit($q['limit-at'] ?? null),
                'max_limit'      => parseRateLimit($q['max-limit'] ?? null),
                'burst_limit'    => $q['burst-limit'] ?? null,
                'dropped'        => $q['dropped'] ?? '0/0',
                'queued_packets' => $q['queued-packets'] ?? '0/0'
            ];
        }
    }

    if ($clientIp) {
        $activeConnections = rosCountFirewallConn($API, $clientIp);

        $pingResp = rosAction($API, '/ping', [
            'address' => $clientIp,
            'count'   => '2'
        ]);
        if (is_array($pingResp) && !isset($pingResp['!trap']) && !empty($pingResp)) {
            $lastPing = end($pingResp);
            if (is_array($lastPing)) {
                $latency = [
                    'target_ip'           => $clientIp,
                    'avg_rtt_ms'          => parseRouterOsRttMs($lastPing['avg-rtt'] ?? null),
                    'min_rtt_ms'          => parseRouterOsRttMs($lastPing['min-rtt'] ?? null),
                    'max_rtt_ms'          => parseRouterOsRttMs($lastPing['max-rtt'] ?? null),
                    'packet_loss_percent' => isset($lastPing['packet-loss']) ? (int)$lastPing['packet-loss'] : null
                ];
            }
        }
    }

    return [
        'queue_limit'        => $queueLimit,
        'active_connections' => $activeConnections,
        'latency'            => $latency
    ];
}

function fetchUsageAndInterface($API, $pppoeUser) {
    $resolved = resolvePppoeInterface($API, $pppoeUser, true);
    $matchedIface = $resolved['matched_iface'];
    $trafficData = $resolved['live_traffic'];
    $dataUsage = null;
    $interfaceHealth = null;

    if ($matchedIface) {
        $ifaceStats = rosGet($API, '/interface', ['name' => $matchedIface]);

        if (!empty($ifaceStats) && isset($ifaceStats[0])) {
            $st = $ifaceStats[0];
            $rxBytes = (float)($st['rx-byte'] ?? 0);
            $txBytes = (float)($st['tx-byte'] ?? 0);
            $totalBytes = $rxBytes + $txBytes;

            $dataUsage = [
                'session_download_mb'    => round($txBytes / 1048576, 2),
                'session_upload_mb'      => round($rxBytes / 1048576, 2),
                'session_total_mb'       => round($totalBytes / 1048576, 2),
                'session_total_human'    => formatBytes($totalBytes),
                'session_download_human' => formatBytes($txBytes),
                'session_upload_human'   => formatBytes($rxBytes),
                'tx_bytes'               => (int)$txBytes,
                'rx_bytes'               => (int)$rxBytes,
                'total_bytes'            => (int)$totalBytes
            ];

            $interfaceHealth = [
                'actual_mtu'        => isset($st['actual-mtu']) ? (int)$st['actual-mtu'] : null,
                'last_link_up_time' => $st['last-link-up-time'] ?? null,
                'link_downs'        => (int)($st['link-downs'] ?? 0),
                'rx_packets'        => (int)($st['rx-packet'] ?? 0),
                'tx_packets'        => (int)($st['tx-packet'] ?? 0),
                'rx_drop'           => (int)($st['rx-drop'] ?? 0),
                'tx_drop'           => (int)($st['tx-drop'] ?? 0),
                'tx_queue_drop'     => (int)($st['tx-queue-drop'] ?? 0),
                'rx_error'          => (int)($st['rx-error'] ?? 0),
                'tx_error'          => (int)($st['tx-error'] ?? 0)
            ];
        }
    }

    return [
        'matched_iface'    => $matchedIface,
        'live_traffic'     => $trafficData,
        'data_usage'       => $dataUsage,
        'interface_health' => $interfaceHealth
    ];
}

function fetchNetworkHealth($API, $enableIntel, $cfToken) {
    $publicIp = null;
    $cloud = rosGet($API, '/ip/cloud');
    if (!empty($cloud) && isset($cloud[0]['public-address'])) {
        $publicIp = $cloud[0]['public-address'];
    }

    if (!$publicIp) {
        $addresses = rosGet($API, '/ip/address');
        if (is_array($addresses)) {
            foreach ($addresses as $addr) {
                $ipRaw = explode('/', $addr['address'] ?? '')[0];
                if (filter_var($ipRaw, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $publicIp = $ipRaw;
                    break;
                }
            }
        }
    }

    $ipIntel = ($enableIntel && $publicIp) ? getIpIntelligence($publicIp, $cfToken) : null;

    $dns = rosGet($API, '/ip/dns');
    $dnsResolver = null;
    if (!empty($dns)) {
        $staticDns  = !empty($dns[0]['servers']) ? explode(',', $dns[0]['servers']) : [];
        $dynamicDns = !empty($dns[0]['dynamic-servers']) ? explode(',', $dns[0]['dynamic-servers']) : [];
        $allDns     = array_values(array_unique(array_filter(array_merge($staticDns, $dynamicDns))));

        $dnsResolver = [
            'servers'      => $allDns,
            'static'       => $dns[0]['servers'] ?? null,
            'dynamic'      => $dns[0]['dynamic-servers'] ?? null,
            'allow_remote' => (isset($dns[0]['allow-remote-requests']) && $dns[0]['allow-remote-requests'] === 'true')
        ];
    }

    $routes = rosGet($API, '/ip/route', ['dst-address' => '0.0.0.0/0']);
    $gatewayStatus = null;
    if (!empty($routes)) {
        $activeRoute = null;
        foreach ($routes as $r) {
            if (isset($r['active']) && $r['active'] === 'true') {
                $activeRoute = $r;
                break;
            }
        }
        if (!$activeRoute && isset($routes[0])) {
            $activeRoute = $routes[0];
        }

        if ($activeRoute) {
            $isReachable = (isset($activeRoute['active']) && $activeRoute['active'] === 'true');
            $gatewayStatus = [
                'gateway_ip' => $activeRoute['gateway'] ?? ($activeRoute['immediate-gw'] ?? null),
                'active'     => $isReachable,
                'status'     => $isReachable ? 'REACHABLE' : 'UNREACHABLE',
                'distance'   => (int)($activeRoute['distance'] ?? 1),
                'comment'    => $activeRoute['comment'] ?? null
            ];
        }
    }

    return [
        'public_ip'       => $publicIp,
        'ip_intelligence' => $ipIntel,
        'dns_resolver'    => $dnsResolver,
        'isp_gateway'     => $gatewayStatus
    ];
}

function fetchRouterSystem($API) {
    $resource = rosGet($API, '/system/resource');
    if (empty($resource)) return null;

    $freeMem = (int)($resource[0]['free-memory'] ?? 0);
    $totalMem = (int)($resource[0]['total-memory'] ?? 0);
    return [
        'cpu_load_percent' => (int)($resource[0]['cpu-load'] ?? 0),
        'free_memory_mb'   => round($freeMem / 1048576, 2),
        'total_memory_mb'  => round($totalMem / 1048576, 2),
        'uptime'           => $resource[0]['uptime'] ?? null,
        'board_name'       => $resource[0]['board-name'] ?? null,
        'version'          => $resource[0]['version'] ?? null
    ];
}

function fetchOltPonStats($oltBaseUrl, $oltToken, $portId, $timeout, $proxy) {
    if ($oltBaseUrl === '' || $oltToken === '') {
        return [
            'ok'          => false,
            'status_code' => 400,
            'error'       => 'OLT_BASE_URL or OLT_TOKEN is not configured in .env'
        ];
    }

    if ($portId <= 0) {
        return [
            'ok'          => false,
            'status_code' => 400,
            'error'       => 'Invalid OLT port_id'
        ];
    }

    $url = "{$oltBaseUrl}/switch_port?form=statistic_monitor&port_id={$portId}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'x-token: ' . $oltToken,
            'Accept: application/json'
        ],
    ]);
    if ($proxy !== '') {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $httpCode === 0) {
        return [
            'ok'          => false,
            'status_code' => 503,
            'error'       => 'Unable to reach HSGQ OLT: ' . ($curlErr ?: 'connection failed')
        ];
    }

    if ($httpCode === 401 || $httpCode === 403) {
        return [
            'ok'          => false,
            'status_code' => 503,
            'error'       => "HSGQ OLT rejected token (HTTP {$httpCode}); update OLT_TOKEN in .env"
        ];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [
            'ok'          => false,
            'status_code' => 502,
            'error'       => "Invalid JSON response from HSGQ OLT (HTTP {$httpCode})"
        ];
    }

    if (($json['code'] ?? null) !== 1 || !isset($json['data']) || !is_array($json['data'])) {
        $msg = $json['message'] ?? 'unknown error';
        return [
            'ok'          => false,
            'status_code' => 503,
            'error'       => "HSGQ OLT error (code=" . ($json['code'] ?? 'null') . "): {$msg} (token may have expired)"
        ];
    }

    $data = $json['data'];
    $times = $data['monitor_time'] ?? [];
    $intervals = $data['interval_time'] ?? [];
    $rxOctets = $data['rx_octets'] ?? [];
    $txOctets = $data['tx_octets'] ?? [];

    $rxRates = [];
    $txRates = [];
    $count = min(count($rxOctets), count($txOctets), count($intervals));
    for ($i = 0; $i < $count; $i++) {
        $iv = (int)$intervals[$i];
        $rx = (float)$rxOctets[$i];
        $tx = (float)$txOctets[$i];
        if ($iv <= 0 || ($rx === 0.0 && $tx === 0.0)) {
            continue;
        }
        $rxRates[] = ($rx * 8) / ($iv * 1000000);
        $txRates[] = ($tx * 8) / ($iv * 1000000);
    }

    $latestInterval = (int)($intervals[0] ?? ($data['interval'] ?? 60));
    $latestRx = (float)($rxOctets[0] ?? 0);
    $latestTx = (float)($txOctets[0] ?? 0);
    $latestRxMbps = $latestInterval > 0 ? round(($latestRx * 8) / ($latestInterval * 1000000), 2) : 0.0;
    $latestTxMbps = $latestInterval > 0 ? round(($latestTx * 8) / ($latestInterval * 1000000), 2) : 0.0;

    $sampleCount = count($rxRates);
    $portEnabled = ((int)($data['status'] ?? 0)) === 1;

    return [
        'ok'      => true,
        'enabled' => $portEnabled,
        'data'    => [
            'port_id'          => (int)($data['port_id'] ?? $portId),
            'port_name'        => 'PON' . str_pad((string)($data['port_id'] ?? $portId), 2, '0', STR_PAD_LEFT),
            'status'           => $portEnabled ? 'Enable' : 'Disable',
            'interval_seconds' => (int)($data['interval'] ?? 60),
            'max_count'        => (int)($data['max_count'] ?? 0),
            'cur_count'        => (int)($data['cur_count'] ?? 0),
            'latest'           => [
                'monitor_time'     => isset($times[0]) ? str_replace('\\/', '/', (string)$times[0]) : null,
                'interval_seconds' => $latestInterval,
                'rx_mbps'          => $latestRxMbps,
                'tx_mbps'          => $latestTxMbps,
                'rx_octets'        => (int)$latestRx,
                'tx_octets'        => (int)$latestTx,
            ],
            'summary'          => [
                'valid_samples'     => $sampleCount,
                'avg_rx_mbps'       => $sampleCount > 0 ? round(array_sum($rxRates) / $sampleCount, 2) : 0.0,
                'avg_tx_mbps'       => $sampleCount > 0 ? round(array_sum($txRates) / $sampleCount, 2) : 0.0,
                'peak_rx_mbps'      => $sampleCount > 0 ? round(max($rxRates), 2) : 0.0,
                'peak_tx_mbps'      => $sampleCount > 0 ? round(max($txRates), 2) : 0.0,
                'min_rx_mbps'       => $sampleCount > 0 ? round(min($rxRates), 2) : 0.0,
                'min_tx_mbps'       => $sampleCount > 0 ? round(min($txRates), 2) : 0.0,
                'rx_error_frames'   => (int)array_sum($data['rx_error_frame'] ?? []),
                'tx_error_frames'   => (int)array_sum($data['tx_error_frame'] ?? []),
                'rx_discard_frames' => (int)array_sum($data['rx_discard_frame'] ?? []),
                'tx_discard_frames' => (int)array_sum($data['tx_discard_frame'] ?? []),
            ]
        ]
    ];
}

function normalizePlatformName($asStr, $org, $isp) {
    $asn = '';
    $asDesc = '';
    if (preg_match('/^(AS\d+)\s*(.*)$/i', (string)$asStr, $m)) {
        $asn = strtoupper($m[1]);
        $asDesc = trim($m[2]);
    }

    $asnMap = [
        'AS15169'  => 'Google / YouTube',
        'AS36040'  => 'Google / YouTube',
        'AS32934'  => 'Meta (FB / IG / WA)',
        'AS63293'  => 'Meta (FB / IG / WA)',
        'AS13335'  => 'Cloudflare',
        'AS209242' => 'Cloudflare',
        'AS396986' => 'ByteDance / TikTok',
        'AS138697' => 'ByteDance / TikTok',
        'AS16509'  => 'Amazon AWS',
        'AS14618'  => 'Amazon AWS',
        'AS8075'   => 'Microsoft / Azure',
        'AS20940'  => 'Akamai',
        'AS16625'  => 'Akamai',
        'AS54113'  => 'Fastly',
        'AS32590'  => 'Valve / Steam',
        'AS132203' => 'Tencent',
        'AS45102'  => 'Alibaba Cloud',
        'AS2906'   => 'Netflix',
        'AS40027'  => 'Netflix',
        'AS62041'  => 'Telegram',
        'AS62014'  => 'Telegram',
        'AS59930'  => 'Telegram',
        'AS136907' => 'Huawei Cloud',
        'AS714'    => 'Apple',
        'AS6185'   => 'Apple',
    ];
    if ($asn !== '' && isset($asnMap[$asn])) {
        return [$asn, $asnMap[$asn]];
    }

    $label = trim((string)($org ?: ($isp ?: ($asDesc ?: 'Unknown'))));
    $lower = strtolower($label);
    $keywordMap = [
        'google'     => 'Google / YouTube',
        'facebook'   => 'Meta (FB / IG / WA)',
        'meta '      => 'Meta (FB / IG / WA)',
        'bytedance'  => 'ByteDance / TikTok',
        'tiktok'     => 'ByteDance / TikTok',
        'cloudflare' => 'Cloudflare',
        'amazon'     => 'Amazon AWS',
        'microsoft'  => 'Microsoft / Azure',
        'akamai'     => 'Akamai',
        'fastly'     => 'Fastly',
        'tencent'    => 'Tencent',
        'alibaba'    => 'Alibaba Cloud',
        'netflix'    => 'Netflix',
        'apple'      => 'Apple',
        'garena'     => 'Garena',
        'moonton'    => 'Moonton / MLBB',
    ];
    foreach ($keywordMap as $kw => $friendly) {
        if (strpos($lower, $kw) !== false) {
            return [$asn ?: '-', $friendly];
        }
    }

    return [$asn ?: '-', $label];
}

function resolvePrefixAsns($prefixSampleIps) {
    $cacheFile = '/tmp/mikrotik_asn_cache.json';
    $now = time();
    $cache = [];
    if (file_exists($cacheFile)) {
        $decoded = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $entry) {
                if (($entry['ts'] ?? 0) + 86400 > $now) {
                    $cache[$k] = $entry;
                }
            }
        }
    }

    $toQuery = [];
    $ipToPrefix = [];
    foreach ($prefixSampleIps as $prefix => $ip) {
        if (!isset($cache[$prefix])) {
            $toQuery[] = $ip;
            $ipToPrefix[$ip] = $prefix;
        }
    }

    if (!empty($toQuery) && function_exists('curl_init')) {
        foreach (array_chunk($toQuery, 100) as $chunk) {
            $ch = curl_init('http://ip-api.com/batch?fields=query,status,as,org,isp,countryCode');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($chunk),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 8,
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $raw) {
                $rows = json_decode($raw, true);
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        $q = $row['query'] ?? null;
                        if (!$q || !isset($ipToPrefix[$q]) || ($row['status'] ?? '') !== 'success') {
                            continue;
                        }
                        $prefix = $ipToPrefix[$q];
                        list($asn, $platform) = normalizePlatformName($row['as'] ?? '', $row['org'] ?? '', $row['isp'] ?? '');
                        $cache[$prefix] = [
                            'ts'       => $now,
                            'asn'      => $asn,
                            'platform' => $platform,
                            'country'  => $row['countryCode'] ?? null,
                        ];
                    }
                }
            }
        }
        @file_put_contents($cacheFile, json_encode($cache));
    }

    return $cache;
}

function fetchGlobalPlatforms($API) {
    $connections = rosGet($API, '/ip/firewall/connection', [
        '.proplist' => 'dst-address,orig-bytes,repl-bytes,protocol'
    ]);
    $dnsCache = rosGet($API, '/ip/dns/cache', [
        '.proplist' => 'name,data,ttl'
    ]);

    $ipDomains = [];
    if (is_array($dnsCache)) {
        foreach ($dnsCache as $entry) {
            $ip = $entry['data'] ?? '';
            $name = $entry['name'] ?? '';
            if ($name === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }
            if (!isset($ipDomains[$ip])) {
                $ipDomains[$ip] = [];
            }
            if (count($ipDomains[$ip]) < 5 && !in_array($name, $ipDomains[$ip], true)) {
                $ipDomains[$ip][] = $name;
            }
        }
    }

    $totalConnections = is_array($connections) ? count($connections) : 0;
    $publicConnections = 0;
    $totalActiveBytes = 0;
    $uniqueIps = [];
    $prefixes = [];

    if (is_array($connections)) {
        foreach ($connections as $c) {
            $rawDst = (string)($c['dst-address'] ?? '');
            $ip = explode(':', $rawDst)[0];
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }

            $publicConnections++;
            $uniqueIps[$ip] = true;
            $origBytes = (int)($c['orig-bytes'] ?? 0);
            $replBytes = (int)($c['repl-bytes'] ?? 0);
            $totalActiveBytes += ($origBytes + $replBytes);

            $octets = explode('.', $ip);
            $prefix = "{$octets[0]}.{$octets[1]}.{$octets[2]}.0/24";
            if (!isset($prefixes[$prefix])) {
                $prefixes[$prefix] = [
                    'sample_ip'   => $ip,
                    'connections' => 0,
                    'orig_bytes'  => 0,
                    'repl_bytes'  => 0,
                    'total_bytes' => 0,
                    'domains'     => [],
                ];
            }
            $prefixes[$prefix]['connections']++;
            $prefixes[$prefix]['orig_bytes'] += $origBytes;
            $prefixes[$prefix]['repl_bytes'] += $replBytes;
            $prefixes[$prefix]['total_bytes'] += ($origBytes + $replBytes);

            if (isset($ipDomains[$ip]) && count($prefixes[$prefix]['domains']) < 3) {
                foreach ($ipDomains[$ip] as $dom) {
                    if (!in_array($dom, $prefixes[$prefix]['domains'], true)) {
                        $prefixes[$prefix]['domains'][] = $dom;
                        if (count($prefixes[$prefix]['domains']) >= 3) break;
                    }
                }
            }
        }
    }

    uasort($prefixes, function ($a, $b) {
        return $b['connections'] <=> $a['connections'];
    });

    $topPrefixSlice = array_slice($prefixes, 0, 100, true);
    $sampleIpMap = [];
    foreach ($topPrefixSlice as $prefix => $info) {
        $sampleIpMap[$prefix] = $info['sample_ip'];
    }
    $asnMap = resolvePrefixAsns($sampleIpMap);

    $platforms = [];
    $coveredConnections = 0;
    $coveredBytes = 0;

    foreach ($topPrefixSlice as $prefix => $info) {
        $coveredConnections += $info['connections'];
        $coveredBytes += $info['total_bytes'];

        $meta = $asnMap[$prefix] ?? ['asn' => '-', 'platform' => "Unresolved ({$prefix})"];
        $key = $meta['platform'];
        if (!isset($platforms[$key])) {
            $platforms[$key] = [
                'platform'       => $meta['platform'],
                'asn'            => $meta['asn'],
                'connections'    => 0,
                'upload_bytes'   => 0,
                'download_bytes' => 0,
                'total_bytes'    => 0,
                'prefixes_count' => 0,
                'sample_domains' => [],
            ];
        }
        $platforms[$key]['connections'] += $info['connections'];
        $platforms[$key]['upload_bytes'] += $info['orig_bytes'];
        $platforms[$key]['download_bytes'] += $info['repl_bytes'];
        $platforms[$key]['total_bytes'] += $info['total_bytes'];
        $platforms[$key]['prefixes_count']++;
        foreach ($info['domains'] as $dom) {
            if (count($platforms[$key]['sample_domains']) < 3 && !in_array($dom, $platforms[$key]['sample_domains'], true)) {
                $platforms[$key]['sample_domains'][] = $dom;
            }
        }
    }

    uasort($platforms, function ($a, $b) {
        return $b['connections'] <=> $a['connections'];
    });

    $topPlatforms = [];
    foreach (array_slice($platforms, 0, 20) as $p) {
        $p['connection_share_percent'] = $publicConnections > 0 ? round(($p['connections'] / $publicConnections) * 100, 1) : 0.0;
        $p['total_human'] = formatBytes($p['total_bytes']);
        $topPlatforms[] = $p;
    }

    return [
        'summary' => [
            'total_connections'          => $totalConnections,
            'public_connections'         => $publicConnections,
            'unique_public_ips'          => count($uniqueIps),
            'unique_prefixes_24'         => count($prefixes),
            'analyzed_top_prefixes'      => count($topPrefixSlice),
            'covered_connections'        => $coveredConnections,
            'coverage_percent'           => $publicConnections > 0 ? round(($coveredConnections / $publicConnections) * 100, 1) : 0.0,
            'total_active_traffic_bytes' => $totalActiveBytes,
            'total_active_traffic_human' => formatBytes($totalActiveBytes),
        ],
        'top_platforms' => $topPlatforms,
    ];
}

if ($routeScope === 'olt') {
    $olt = fetchOltPonStats($oltBaseUrl, $oltToken, $oltPortId, $timeout, $proxy);
    if (!$olt['ok']) {
        respond($olt['status_code'], [
            'status'      => 'DOWN',
            'online'      => false,
            'timestamp'   => date('c'),
            'diagnostics' => [
                'olt_reachable'  => false,
                'overall_health' => 'OLT_UNREACHABLE_OR_AUTH_FAILED',
            ],
            'message'     => $olt['error'],
        ]);
    }

    $portEnabled = $olt['enabled'];
    respond($portEnabled ? 200 : 503, [
        'status'      => $portEnabled ? 'UP' : 'DOWN',
        'online'      => $portEnabled,
        'timestamp'   => date('c'),
        'diagnostics' => [
            'olt_reachable'  => true,
            'port_status'    => $olt['data']['status'],
            'overall_health' => $portEnabled ? 'ALL_SYSTEMS_OPERATIONAL' : 'OLT_PORT_DISABLED',
        ],
        'olt_pon'     => $olt['data'],
    ]);
}

if (in_array($routeScope, ['all', 'connection', 'usage'], true) && $pppoeUser === '') {
    respond(400, [
        'status'    => 'ERROR',
        'online'    => false,
        'timestamp' => date('c'),
        'message'   => 'Target PPPoE user is not configured (set MIKROTIK_PPPOE_USER or pass ?user=...)'
    ]);
}

$clientTimeout = ($routeScope === 'platforms') ? max($timeout, 15) : $timeout;
$API = new RouterosRestClient($restUrl, $username, $password, $clientTimeout, $proxy);
$connected = $API->connect();

if ($connected) {
    try {
        if ($routeScope === 'platforms') {
            $platformStats = fetchGlobalPlatforms($API);
            $API->disconnect();

            respond(200, [
                'status'      => 'UP',
                'online'      => true,
                'timestamp'   => date('c'),
                'diagnostics' => [
                    'router_connected' => true,
                    'overall_health'   => 'ALL_SYSTEMS_OPERATIONAL',
                ],
                'platforms'   => $platformStats,
            ]);
        }

        if ($routeScope === 'connection') {
            $active = fetchPppoeActive($API, $pppoeUser);
            $secretInfo = fetchPppoeSecretAndLimit($API, $pppoeUser);
            $isOnline = !empty($active);
            $extras = ['queue_limit' => null, 'active_connections' => null, 'latency' => null];
            if ($isOnline) {
                $iface = resolvePppoeInterface($API, $pppoeUser, false);
                $extras = fetchConnectionExtras($API, $iface['matched_iface'], $active[0]['address'] ?? null);
            }
            $API->disconnect();

            respond($isOnline ? 200 : 503, [
                'status'    => $isOnline ? 'UP' : 'DOWN',
                'online'    => $isOnline,
                'timestamp' => date('c'),
                'diagnostics' => [
                    'router_connected'  => true,
                    'router_status'     => 'ONLINE',
                    'user_pppoe_status' => $isOnline ? 'ONLINE' : 'OFFLINE',
                    'overall_health'    => $isOnline ? 'ALL_SYSTEMS_OPERATIONAL' : 'USER_DISCONNECTED'
                ],
                'home_connection' => [
                    'user'               => $pppoeUser,
                    'ip_address'         => $active[0]['address'] ?? null,
                    'uptime'             => $active[0]['uptime'] ?? null,
                    'caller_id'          => $active[0]['caller-id'] ?? null,
                    'session_id'         => $active[0]['session-id'] ?? null,
                    'radius'             => isset($active[0]['radius']) ? ($active[0]['radius'] === 'true') : null,
                    'profile_name'       => $secretInfo['profile_name'],
                    'bandwidth_limit'    => $secretInfo['bandwidth_limit'],
                    'queue_limit'        => $extras['queue_limit'],
                    'active_connections' => $extras['active_connections'],
                    'latency'            => $extras['latency'],
                    'secret_info'        => $secretInfo['secret_info']
                ]
            ]);
        }

        if ($routeScope === 'usage') {
            $active = fetchPppoeActive($API, $pppoeUser);
            $isOnline = !empty($active);
            $usage = $isOnline
                ? fetchUsageAndInterface($API, $pppoeUser)
                : ['live_traffic' => null, 'data_usage' => null, 'interface_health' => null];
            $API->disconnect();

            respond($isOnline ? 200 : 503, [
                'status'    => $isOnline ? 'UP' : 'DOWN',
                'online'    => $isOnline,
                'timestamp' => date('c'),
                'diagnostics' => [
                    'router_connected'  => true,
                    'router_status'     => 'ONLINE',
                    'user_pppoe_status' => $isOnline ? 'ONLINE' : 'OFFLINE',
                    'overall_health'    => $isOnline ? 'ALL_SYSTEMS_OPERATIONAL' : 'USER_DISCONNECTED'
                ],
                'home_connection' => [
                    'user'             => $pppoeUser,
                    'uptime'           => $active[0]['uptime'] ?? null,
                    'data_usage'       => $usage['data_usage'],
                    'live_traffic'     => $usage['live_traffic'],
                    'interface_health' => $usage['interface_health']
                ]
            ]);
        }

        if ($routeScope === 'network') {
            $network = fetchNetworkHealth($API, $enableIntel, $cfToken);
            $API->disconnect();
            $gwStatusStr = $network['isp_gateway']['status'] ?? 'UNKNOWN';

            respond(200, [
                'status'    => $gwStatusStr === 'UNREACHABLE' ? 'DEGRADED' : 'UP',
                'online'    => $gwStatusStr !== 'UNREACHABLE',
                'timestamp' => date('c'),
                'diagnostics' => [
                    'router_connected'   => true,
                    'router_status'      => 'ONLINE',
                    'isp_gateway_status' => $gwStatusStr,
                    'overall_health'     => $gwStatusStr === 'UNREACHABLE' ? 'ISP_GATEWAY_DOWN' : 'ALL_SYSTEMS_OPERATIONAL'
                ],
                'network_health' => $network
            ]);
        }

        if ($routeScope === 'router') {
            $routerHealth = fetchRouterSystem($API);
            $API->disconnect();

            respond(200, [
                'status'    => 'UP',
                'online'    => true,
                'timestamp' => date('c'),
                'diagnostics' => [
                    'router_connected' => true,
                    'router_status'    => 'ONLINE',
                    'overall_health'   => 'ALL_SYSTEMS_OPERATIONAL'
                ],
                'network_health' => [
                    'router_system' => $routerHealth
                ]
            ]);
        }

        // Default '/api' full combined healthcheck
        $active = fetchPppoeActive($API, $pppoeUser);
        $secretInfo = fetchPppoeSecretAndLimit($API, $pppoeUser);
        $isOnline = !empty($active);

        $usage = $isOnline
            ? fetchUsageAndInterface($API, $pppoeUser)
            : ['matched_iface' => null, 'live_traffic' => null, 'data_usage' => null, 'interface_health' => null];

        $extras = $isOnline
            ? fetchConnectionExtras($API, $usage['matched_iface'], $active[0]['address'] ?? null)
            : ['queue_limit' => null, 'active_connections' => null, 'latency' => null];

        $network = fetchNetworkHealth($API, $enableIntel, $cfToken);
        $routerHealth = fetchRouterSystem($API);
        $API->disconnect();

        $userStatus  = $isOnline ? 'ONLINE' : 'OFFLINE';
        $gwStatusStr = $network['isp_gateway']['status'] ?? 'UNKNOWN';
        $overallHealth = 'ALL_SYSTEMS_OPERATIONAL';
        if ($userStatus === 'OFFLINE') {
            $overallHealth = 'USER_DISCONNECTED';
        } elseif ($gwStatusStr === 'UNREACHABLE') {
            $overallHealth = 'ISP_GATEWAY_DOWN';
        }

        respond($isOnline ? 200 : 503, [
            'status'    => $isOnline ? 'UP' : 'DOWN',
            'online'    => $isOnline,
            'timestamp' => date('c'),
            'diagnostics' => [
                'router_connected'   => true,
                'router_status'      => 'ONLINE',
                'user_pppoe_status'  => $userStatus,
                'isp_gateway_status' => $gwStatusStr,
                'overall_health'     => $overallHealth
            ],
            'home_connection' => [
                'user'               => $pppoeUser,
                'ip_address'         => $active[0]['address'] ?? null,
                'uptime'             => $active[0]['uptime'] ?? null,
                'caller_id'          => $active[0]['caller-id'] ?? null,
                'session_id'         => $active[0]['session-id'] ?? null,
                'radius'             => isset($active[0]['radius']) ? ($active[0]['radius'] === 'true') : null,
                'profile_name'       => $secretInfo['profile_name'],
                'bandwidth_limit'    => $secretInfo['bandwidth_limit'],
                'queue_limit'        => $extras['queue_limit'],
                'data_usage'         => $usage['data_usage'],
                'live_traffic'       => $usage['live_traffic'],
                'interface_health'   => $usage['interface_health'],
                'active_connections' => $extras['active_connections'],
                'latency'            => $extras['latency'],
                'secret_info'        => $secretInfo['secret_info']
            ],
            'network_health' => array_merge($network, [
                'router_system' => $routerHealth
            ])
        ]);

    } catch (Exception $e) {
        $API->disconnect();
        respond(500, ['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
} else {
    respond(503, [
        'status'    => 'DOWN',
        'online'    => false,
        'user'      => $pppoeUser,
        'timestamp' => date('c'),
        'diagnostics' => [
            'router_connected'   => false,
            'router_status'      => 'OFFLINE',
            'user_pppoe_status'  => 'UNKNOWN',
            'isp_gateway_status' => 'UNKNOWN',
            'overall_health'     => 'ROUTER_UNREACHABLE'
        ],
        'message'   => 'Unable to connect to RouterOS API'
    ]);
}
