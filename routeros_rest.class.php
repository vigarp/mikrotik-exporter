<?php
/**
 * Lightweight RouterOS v7 REST API Client with optional SOCKS5 proxy support.
 */

class RouterosRestClient
{
    public $baseUrl;
    public $username;
    public $password;
    public $timeout = 5;
    public $proxy = null;
    public $sslVerify = false;
    public $lastError = null;

    public function __construct($baseUrl, $username, $password, $timeout = 5, $proxy = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->timeout = (int)$timeout;
        $this->proxy = $proxy ? trim($proxy) : null;
    }

    public function connect()
    {
        $res = $this->request('GET', '/system/resource');
        return is_array($res) && !isset($res['!trap']);
    }

    public function disconnect()
    {
        // Stateless HTTP client
    }

    public function get($path, array $query = [])
    {
        $urlPath = '/' . ltrim($path, '/');
        if (!empty($query)) {
            $urlPath .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $data = $this->request('GET', $urlPath);
        if (!is_array($data) || isset($data['!trap'])) {
            return $data;
        }
        // Normalize single-object endpoints (e.g. /system/resource, /ip/dns, /ip/cloud) into [0 => ...]
        if (!empty($data) && !array_is_list($data)) {
            return [$data];
        }
        return $data;
    }

    public function post($path, array $payload = [])
    {
        $urlPath = '/' . ltrim($path, '/');
        return $this->request('POST', $urlPath, $payload);
    }

    private function request($method, $urlPath, $payload = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $urlPath);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);

        if (!empty($this->proxy)) {
            $proxyStr = $this->proxy;
            if (strpos($proxyStr, '://') === false) {
                $proxyStr = 'socks5h://' . $proxyStr;
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxyStr);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? new stdClass()));
        }

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode === 0) {
            $this->lastError = $curlErr ?: 'Connection failed';
            return ['!trap' => [['message' => $this->lastError]]];
        }

        $decoded = json_decode($body, true);
        if ($httpCode >= 400) {
            $msg = is_array($decoded) ? ($decoded['detail'] ?? ($decoded['message'] ?? "HTTP $httpCode")) : "HTTP $httpCode";
            $this->lastError = $msg;
            return ['!trap' => [['message' => $msg]]];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
