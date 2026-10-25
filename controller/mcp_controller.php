<?php
namespace topofgames\phpbbmcp\controller;

use Symfony\Component\HttpFoundation\Response;

class mcp_controller
{
    private $config;
    private $request;
    private $keys;
    private $reader;
    private $transport;

    public function __construct($config, $request, $keys, $reader, $transport)
    {
        $this->config = $config;
        $this->request = $request;
        $this->keys = $keys;
        $this->reader = $reader;
        $this->transport = $transport;
    }

    private function json($data, $status = 200)
    {
        return new Response(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $status, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }

    private function error($id, $code, $message, $status = 200)
    {
        return $this->json(['jsonrpc' => '2.0', 'id' => $id,
            'error' => ['code' => $code, 'message' => $message]], $status);
    }

    private function success($id, array $result, $modern)
    {
        if ($modern) {
            $result = array_merge(['resultType' => 'complete'], $result);
            $result['_meta'] = [
                'io.modelcontextprotocol/serverInfo' => [
                    'name' => 'phpbb-mcp', 'version' => '0.1.0-alpha',
                ],
            ];
        }
        return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    public function handle()
    {
        if (empty($this->config['topofgames_phpbbmcp_enabled'])) {
            return $this->error(null, -32000, 'MCP endpoint disabled', 503);
        }
        // HTTPS is required. Reverse proxies must prevent direct access and
        // sanitize forwarded headers; phpBB is_secure() trusts X-Forwarded-Proto.
        if (!$this->request->is_secure() ||
            strtolower((string) ($this->config['server_protocol'] ?? '')) !== 'https://') {
            return $this->error(null, -32000, 'HTTPS required', 403);
        }

        // Compare Origin to the administrator-configured board hostname.
        // Unauthenticated CORS preflights are deliberately unsupported.
        $origin = (string) $this->request->header('Origin', '');
        if (!$this->transport->origin_allowed($origin,
            (string) ($this->config['server_name'] ?? ''),
            (int) ($this->config['server_port'] ?? 443))) {
            return $this->error(null, -32000, 'Origin not allowed', 403);
        }

        $authorization = (string) $this->request->header('Authorization', '');
        if (!preg_match('/^Bearer (pmcp_[A-Za-z0-9_-]{43})$/D', $authorization, $matches)) {
            return $this->error(null, -32001, 'Unauthorized', 401);
        }
        $key = $this->keys->authorize($matches[1]);
        if ($key === null) {
            return $this->error(null, -32001, 'Unauthorized', 401);
        }
        if (isset($key['rate_limited'])) {
            return $this->error(null, -32000, 'Rate limited', 429);
        }

        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) {
            return $this->error(null, -32600, 'Maximum request body: 64 KiB', 413);
        }
        $req = json_decode($raw, true);
        if (!is_array($req) || !$req || isset($req[0]) ||
            ($req['jsonrpc'] ?? null) !== '2.0' ||
            !is_string($req['method'] ?? null)) {
            return $this->error(null, -32600, 'Invalid JSON-RPC request', 400);
        }

        $id = $req['id'] ?? null;
        if ($id !== null && !is_string($id) && !is_int($id)) {
            return $this->error(null, -32600, 'Invalid request ID', 400);
        }
        $version = (string) $this->request->header('MCP-Protocol-Version', '');
        $failure = $this->transport->validate(
            $version,
            (string) $this->request->header('Mcp-Method', ''),
            (string) $this->request->header('Mcp-Name', ''),
            (string) $this->request->header('Accept', ''),
            (string) $this->request->header('Content-Type', ''),
            $req
        );
        if ($failure !== null) {
            return $this->error($id, $failure['code'], $failure['message'], $failure['status']);
        }
        $modern = $version === '2026-07-28';
        $method = $req['method'];

        if (!$modern && $method === 'notifications/initialized' && !array_key_exists('id', $req)) {
            return new Response('', 202, ['Cache-Control' => 'no-store']);
        }
        if (!array_key_exists('id', $req)) {
            return $this->error(null, -32600, 'Unsupported notification', 400);
        }

        if ($modern && $method === 'server/discover') {
            return $this->success($id, [
                'supportedVersions' => $this->transport->supported_versions(),
                'capabilities' => ['tools' => ['listChanged' => false]],
                'instructions' => 'Public forum read tools. Only capabilities granted to this token are exposed.',
                'ttlMs' => 0,
                'cacheScope' => 'private',
            ], true);
        }

        if (!$modern && $method === 'initialize') {
            $requested = $req['params']['protocolVersion'] ?? null;
            $selected = in_array($requested, $this->transport->supported_versions(), true) &&
                $requested !== '2026-07-28' ? $requested : '2025-06-18';
            return $this->success($id, [
                'protocolVersion' => $selected,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'phpbb-mcp', 'version' => '0.1.0-alpha'],
            ], false);
        }

        if (!$modern && $method === 'ping') {
            return $this->success($id, [], false);
        }

        if ($method === 'tools/list') {
            $tools = array_values(array_filter($this->reader->specifications(), function ($tool) use ($key) {
                return in_array($tool['_meta']['topofgames/scope'], $key['scopes'], true);
            }));
            $result = ['tools' => $tools];
            if ($modern) {
                $result['ttlMs'] = 0;
                $result['cacheScope'] = 'private';
            }
            return $this->success($id, $result, $modern);
        }

        if ($method === 'tools/call') {
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
                return $this->success($id, [
                    'content' => [
                        ['type' => 'text', 'text' => json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE)],
                    ],
                    'structuredContent' => $result,
                ], $modern);
            } catch (\InvalidArgumentException $e) {
                return $this->error($id, -32602, $e->getMessage());
            } catch (\Throwable $e) {
                error_log('phpBB MCP execution failure: ' . get_class($e));
                return $this->success($id, [
                    'content' => [['type' => 'text', 'text' => 'Tool execution failed']],
                    'isError' => true,
                ], $modern);
            }
        }

        return $this->error($id, -32601, 'Method not found', $modern ? 404 : 200);
    }
}
