<?php
namespace topofgames\phpbbmcp\service;

/**
 * Pure transport checks that can be tested without booting phpBB.
 *
 * 2026-07-28 uses per-request version/capabilities metadata and mandatory
 * mirrored HTTP headers. Two older Streamable HTTP revisions are supported
 * as a compatibility layer for existing MCP clients.
 */
class transport_validator
{
    const CURRENT = '2026-07-28';
    const LEGACY = ['2025-11-25', '2025-06-18'];

    public function supported_versions()
    {
        return array_merge([self::CURRENT], self::LEGACY);
    }

    public function origin_allowed($origin, $server_name, $server_port)
    {
        if ($origin === '') {
            return true;
        }
        if (!is_string($origin) || strlen($origin) > 512 ||
            preg_match('/[\x00-\x20\x7f]/', $origin)) {
            return false;
        }
        $url = parse_url($origin);
        if (!is_array($url) || strtolower($url['scheme'] ?? '') !== 'https' ||
            !isset($url['host']) || isset($url['user']) || isset($url['pass']) ||
            isset($url['path']) || isset($url['query']) || isset($url['fragment'])) {
            return false;
        }
        $host = strtolower(rtrim((string) $server_name, '.'));
        $origin_host = strtolower(rtrim((string) $url['host'], '.'));
        // The trusted phpBB board URL comes from ACP configuration, never Host/XFH.
        return $host !== '' && $origin_host === $host &&
            (int) ($url['port'] ?? 443) === (int) ($server_port ?: 443);
    }

    public function validate($version, $method_header, $name_header, $accept, $content_type, array $body)
    {
        if (stripos($content_type, 'application/json') !== 0) {
            return ['status' => 415, 'code' => -32600, 'message' => 'JSON Content-Type required'];
        }
        if (stripos($accept, 'application/json') === false ||
            stripos($accept, 'text/event-stream') === false) {
            return ['status' => 406, 'code' => -32600, 'message' => 'Accept must include JSON and event-stream'];
        }
        $method = $body['method'] ?? null;
        if (!is_string($method) || $method === '') {
            return ['status' => 400, 'code' => -32600, 'message' => 'Missing method'];
        }

        if ($version === self::CURRENT) {
            $meta = $body['params']['_meta'] ?? null;
            if (!is_array($meta) ||
                !isset($meta['io.modelcontextprotocol/protocolVersion']) ||
                !array_key_exists('io.modelcontextprotocol/clientCapabilities', $meta) ||
                !is_array($meta['io.modelcontextprotocol/clientCapabilities'])) {
                return ['status' => 400, 'code' => -32600, 'message' => 'Missing MCP request metadata'];
            }
            if ($meta['io.modelcontextprotocol/protocolVersion'] !== self::CURRENT ||
                $method_header !== $method) {
                return ['status' => 400, 'code' => -32020, 'message' => 'MCP header mismatch'];
            }
            if ($method === 'tools/call') {
                $name = $body['params']['name'] ?? null;
                if (!is_string($name) || $this->decode_header_value($name_header) !== $name) {
                    return ['status' => 400, 'code' => -32020, 'message' => 'Mcp-Name mismatch'];
                }
            }
            return null;
        }

        // Existing clients negotiate through initialize and then send one of
        // these legacy versions. Omitted header is permitted on initialize.
        if (in_array($version, self::LEGACY, true) ||
            ($version === '' && $method === 'initialize')) {
            return null;
        }
        return [
            'status' => 400,
            'code' => -32022,
            'message' => 'Unsupported protocol version; supported: ' .
                implode(', ', $this->supported_versions()),
        ];
    }

    private function decode_header_value($raw)
    {
        if (!is_string($raw)) {
            return null;
        }
        if (strpos($raw, '=?base64?') === 0 && substr($raw, -2) === '?=') {
            return base64_decode(substr($raw, 9, -2), true);
        }
        if ($raw === '' || preg_match('/[^\x21-\x7e]/', $raw)) {
            return null;
        }
        return $raw;
    }
}
