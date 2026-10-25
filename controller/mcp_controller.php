<?php
namespace topofgames\phpbbmcp\controller;

use Symfony\Component\HttpFoundation\Response;

class mcp_controller
{
    private $config;
    private $request;
    private $keys;
    private $reader;

    public function __construct($config, $request, $keys, $reader)
    {
        $this->config = $config;
        $this->request = $request;
        $this->keys = $keys;
        $this->reader = $reader;
    }

    private function json($data, $status = 200)
    {
        return new Response(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $status, ['Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function error($id, $code, $message, $status = 200)
    {
        return $this->json(['jsonrpc' => '2.0', 'id' => $id,
            'error' => ['code' => $code, 'message' => $message]], $status);
    }

    public function handle()
    {
        if (empty($this->config['topofgames_phpbbmcp_enabled'])) {
            return $this->error(null, -32000, 'MCP endpoint disabled', 503);
        }
        // Reject non-HTTPS traffic even if the reverse proxy terminates TLS.
        // Configure your trusted proxy in phpBB; do not trust arbitrary forwarded headers.
        if (!$this->request->is_secure()) {
            return $this->error(null, -32000, 'HTTPS required', 403);
        }
        $auth = (string) $this->request->header('Authorization', '');
        if (!preg_match('/^Bearer (pmcp_[A-Za-z0-9_-]{43})$/Di', $auth, $matches)) {
            return $this->error(null, -32001, 'Unauthorized', 401);
        }
        $key = $this->keys->authorize($matches[1]);
        if ($key === null) { return $this->error(null, -32001, 'Unauthorized', 401); }
        if (isset($key['rate_limited'])) { return $this->error(null, -32000, 'Rate limited', 429); }

        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) {
            return $this->error(null, -32600, 'Maximum request body: 64 KiB', 413);
        }
        $req = json_decode($raw, true);
        if (!is_array($req) || array_is_list($req) || ($req['jsonrpc'] ?? null) !== '2.0' ||
            !is_string($req['method'] ?? null)) {
            return $this->error(null, -32600, 'Invalid JSON-RPC request', 400);
        }
        $id = $req['id'] ?? null;
        if ($id !== null && !is_string($id) && !is_int($id)) {
            return $this->error(null, -32600, 'Invalid request ID', 400);
        }
        $method = $req['method'];
        if ($method === 'notifications/initialized' && !array_key_exists('id', $req)) {
            return new Response('', 202, ['Cache-Control' => 'no-store']);
        }
        if (!array_key_exists('id', $req)) {
            return $this->error(null, -32600, 'Unsupported notification', 400);
        }
        switch ($method) {
            case 'initialize':
                return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => ['name' => 'phpbb-mcp', 'version' => '0.1.0'],
                ]]);
            case 'ping':
                return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) []]);
            case 'tools/list':
                $tools = array_values(array_filter($this->reader->specifications(), function ($tool) use ($key) {
                    return in_array($tool['_meta']['topofgames/scope'], $key['scopes'], true);
                }));
                return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $tools]]);
            case 'tools/call':
                $params = $req['params'] ?? [];
                if (!is_array($params) || !is_string($params['name'] ?? null) ||
                    !is_array($params['arguments'] ?? [])) {
                    return $this->error($id, -32602, 'Invalid tool parameters');
                }
                $scope = $this->reader->scope_for($params['name']);
                if ($scope === null || !in_array($scope, $key['scopes'], true)) {
                    return $this->error($id, -32601, 'Tool unavailable');
                }
                try {
                    $result = $this->reader->run($params['name'], $params['arguments'] ?? []);
                    return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                        'content' => [['type' => 'text', 'text' => json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE)]],
                        'structuredContent' => $result,
                    ]]);
                } catch (\InvalidArgumentException $e) {
                    return $this->error($id, -32602, $e->getMessage());
                } catch (\Throwable $e) {
                    error_log('phpBB MCP tool exception: ' . get_class($e));
                    return $this->error($id, -32603, 'Tool execution failed');
                }
        }
        return $this->error($id, -32601, 'Method not found');
    }
}
