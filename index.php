<?php
/**
 * MikroTik PPPoE & Infrastructure Healthcheck Exporter for Uptime Kuma
 */

require_once(__DIR__ . '/routeros_api.class.php');

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
$host        = getenv('MIKROTIK_HOST') ?: ($_ENV['MIKROTIK_HOST'] ?? ($_SERVER['MIKROTIK_HOST'] ?? '192.168.88.1'));
$port        = (int)(getenv('MIKROTIK_PORT') ?: ($_ENV['MIKROTIK_PORT'] ?? ($_SERVER['MIKROTIK_PORT'] ?? 8728)));
$username    = getenv('MIKROTIK_USER') ?: ($_ENV['MIKROTIK_USER'] ?? ($_SERVER['MIKROTIK_USER'] ?? 'admin'));
$password    = getenv('MIKROTIK_PASS') ?: ($_ENV['MIKROTIK_PASS'] ?? ($_SERVER['MIKROTIK_PASS'] ?? ''));
$pppoeUser   = $_GET['user'] ?? (getenv('MIKROTIK_PPPOE_USER') ?: ($_ENV['MIKROTIK_PPPOE_USER'] ?? ($_SERVER['MIKROTIK_PPPOE_USER'] ?? 'vigarrRT03')));
$cfToken     = getenv('CLOUDFLARE_RADAR_TOKEN') ?: ($_ENV['CLOUDFLARE_RADAR_TOKEN'] ?? ($_SERVER['CLOUDFLARE_RADAR_TOKEN'] ?? null));
$enableIntel = (isset($_GET['enrich']) && ($_GET['enrich'] === 'true' || $_GET['enrich'] === '1'))
             || (isset($_GET['intel']) && ($_GET['intel'] === 'true' || $_GET['intel'] === '1'))
             || (getenv('ENABLE_EXTERNAL_INTEL') === 'true' || ($_ENV['ENABLE_EXTERNAL_INTEL'] ?? '') === 'true');
$timeout     = (int)(getenv('MIKROTIK_TIMEOUT') ?: ($_ENV['MIKROTIK_TIMEOUT'] ?? ($_SERVER['MIKROTIK_TIMEOUT'] ?? 5)));

header('Content-Type: application/json; charset=utf-8');

function respond($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Helper to format rate limit string like "10M/10M" or bps into Mbps
function parseRateLimit($rateStr) {
    if (!$rateStr) return null;
    $parts = explode('/', $rateStr);
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

// Helper to fetch IP Intelligence (ASN, ISP, Region) via Cloudflare Radar API with IP-API fallback
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

    // Try Cloudflare Radar API if token is provided
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

    // Fallback: IP-API lookup
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

$API = new RouterosAPI();
$API->port = $port;
$API->timeout = $timeout;

if ($API->connect($host, $username, $password)) {
    try {
        // 1. Query Active PPPoE Session
        $API->write('/ppp/active/print', false);
        $API->write('?name=' . $pppoeUser);
        $active = $API->read();

        // 2. Query PPPoE Secret & Profile for Bandwidth Limit info
        $API->write('/ppp/secret/print', false);
        $API->write('?name=' . $pppoeUser);
        $secret = $API->read();

        $profileName = $secret[0]['profile'] ?? null;
        $rateLimit = null;
        
        if (!empty($profileName)) {
            $API->write('/ppp/profile/print', false);
            $API->write('?name=' . $profileName);
            $profile = $API->read();
            $rateLimitStr = $profile[0]['rate-limit'] ?? ($secret[0]['rate-limit'] ?? null);
            $rateLimit = parseRateLimit($rateLimitStr);
        }

        // 3. Monitor Live Traffic if user is online
        $trafficData = null;
        if (!empty($active)) {
            $interfaceCandidates = [
                '<pppoe-' . $pppoeUser . '>',
                'pppoe-' . $pppoeUser,
                '<' . $pppoeUser . '>',
                $pppoeUser
            ];

            foreach ($interfaceCandidates as $ifaceName) {
                $API->write('/interface/monitor-traffic', false);
                $API->write('=interface=' . $ifaceName, false);
                $API->write('=once=');
                $traffic = $API->read();

                if (!empty($traffic) && !isset($traffic['!trap'])) {
                    $txBps = (int)($traffic[0]['tx-bits-per-second'] ?? 0);
                    $rxBps = (int)($traffic[0]['rx-bits-per-second'] ?? 0);
                    $trafficData = [
                        'interface_name' => $ifaceName,
                        'upload_kbps'   => round($txBps / 1000, 2),
                        'download_kbps' => round($rxBps / 1000, 2),
                        'tx_bps'        => $txBps,
                        'rx_bps'        => $rxBps
                    ];
                    break;
                }
            }
        }

        // 4. Query Public IP (via IP Cloud or IP Address list fallback)
        $publicIp = null;
        $API->write('/ip/cloud/print');
        $cloud = $API->read();
        if (!empty($cloud) && isset($cloud[0]['public-address'])) {
            $publicIp = $cloud[0]['public-address'];
        }

        if (!$publicIp) {
            $API->write('/ip/address/print');
            $addresses = $API->read();
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

        // Fetch IP Intelligence only if enabled via ?enrich=true or ENABLE_EXTERNAL_INTEL=true
        $ipIntel = null;
        if ($enableIntel && $publicIp) {
            $ipIntel = getIpIntelligence($publicIp, $cfToken);
        }

        // 5. Query DNS Resolver Configuration
        $API->write('/ip/dns/print');
        $dns = $API->read();

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

        // 6. Query ISP Gateway / Default Route Status (0.0.0.0/0)
        $API->write('/ip/route/print', false);
        $API->write('?dst-address=0.0.0.0/0');
        $routes = $API->read();
        
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

        // 7. Query Router System Resource
        $API->write('/system/resource/print');
        $resource = $API->read();

        $routerHealth = null;
        if (!empty($resource)) {
            $freeMem = (int)($resource[0]['free-memory'] ?? 0);
            $totalMem = (int)($resource[0]['total-memory'] ?? 0);
            $routerHealth = [
                'cpu_load_percent' => (int)($resource[0]['cpu-load'] ?? 0),
                'free_memory_mb'   => round($freeMem / 1048576, 2),
                'total_memory_mb'  => round($totalMem / 1048576, 2),
                'uptime'           => $resource[0]['uptime'] ?? null,
                'board_name'       => $resource[0]['board-name'] ?? null,
                'version'          => $resource[0]['version'] ?? null
            ];
        }

        $API->disconnect();

        // Evaluate Diagnostic Statuses for 3 Failure Scenarios
        $isOnline    = !empty($active);
        $userStatus  = $isOnline ? 'ONLINE' : 'OFFLINE';
        $gwStatusStr = $gatewayStatus['status'] ?? 'UNKNOWN';
        $routerStatus= 'ONLINE';

        $overallHealth = 'ALL_SYSTEMS_OPERATIONAL';
        if ($userStatus === 'OFFLINE') {
            $overallHealth = 'USER_DISCONNECTED';
        } elseif ($gwStatusStr === 'UNREACHABLE') {
            $overallHealth = 'ISP_GATEWAY_DOWN';
        }

        $statusCode = $isOnline ? 200 : 503;

        respond($statusCode, [
            'status'    => $isOnline ? 'UP' : 'DOWN',
            'online'    => $isOnline,
            'timestamp' => date('c'),
            'diagnostics' => [
                'router_connected'   => true,
                'router_status'      => $routerStatus,
                'user_pppoe_status'  => $userStatus,
                'isp_gateway_status' => $gwStatusStr,
                'overall_health'     => $overallHealth
            ],
            'home_connection' => [
                'user'            => $pppoeUser,
                'ip_address'      => $active[0]['address'] ?? null,
                'uptime'          => $active[0]['uptime'] ?? null,
                'caller_id'       => $active[0]['caller-id'] ?? null,
                'profile_name'    => $profileName,
                'bandwidth_limit' => $rateLimit,
                'live_traffic'    => $trafficData
            ],
            'network_health' => [
                'public_ip'       => $publicIp,
                'ip_intelligence' => $ipIntel,
                'dns_resolver'    => $dnsResolver,
                'isp_gateway'     => $gatewayStatus,
                'router_system'   => $routerHealth
            ]
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
