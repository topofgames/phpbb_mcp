<?php
namespace topofgames\phpbbmcp\service;

/**
 * Read-only public/approved content. Never returns passwords, emails, IPs,
 * private forums, unapproved posts, or data from password-locked forums.
 */
class reader
{
    private $db;
    private $prefix;
    private $guest_auth;

    public function __construct($db, $table_prefix)
    {
        $this->db = $db;
        $this->prefix = $table_prefix;
    }

    public function specifications()
    {
        return [
            $this->definition('forums.list', 'List publicly readable forums', 'forums:read', [
                'limit' => $this->integer(), 'offset' => $this->integer(),
            ]),
            $this->definition('forums.get', 'Get a publicly readable forum', 'forums:read', [
                'forum_id' => $this->integer(),
            ], ['forum_id']),
            $this->definition('topics.list', 'List approved topics in a public forum', 'topics:read', [
                'forum_id' => $this->integer(), 'limit' => $this->integer(), 'offset' => $this->integer(),
            ], ['forum_id']),
            $this->definition('topics.get', 'Get one approved topic', 'topics:read', [
                'topic_id' => $this->integer(),
            ], ['topic_id']),
            $this->definition('posts.list', 'List approved posts in an approved topic', 'posts:read', [
                'topic_id' => $this->integer(), 'limit' => $this->integer(), 'offset' => $this->integer(),
            ], ['topic_id']),
            $this->definition('posts.get', 'Get one approved post', 'posts:read', [
                'post_id' => $this->integer(),
            ], ['post_id']),
            $this->definition('posts.search', 'Search approved posts in public forums', 'posts:read', [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                'limit' => $this->integer(),
            ], ['query']),
            $this->definition('users.get_public', 'Get a public user profile, never email/IP', 'users:read', [
                'user_id' => $this->integer(),
            ], ['user_id']),
        ];
    }

    private function integer()
    {
        return ['type' => 'integer', 'minimum' => 0];
    }

    private function definition($name, $description, $scope, $properties, $required = [])
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
            'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false],
            '_meta' => ['topofgames/scope' => $scope],
        ];
    }

    public function scope_for($name)
    {
        if (strpos($name, 'forums.') === 0) { return 'forums:read'; }
        if (strpos($name, 'topics.') === 0) { return 'topics:read'; }
        if (strpos($name, 'posts.') === 0) { return 'posts:read'; }
        if (strpos($name, 'users.') === 0) { return 'users:read'; }
        return null;
    }

    private function guest_can_read($forum_id)
    {
        $forum_id = (int) $forum_id;
        if (!$this->guest_auth) {
            $this->guest_auth = new \phpbb\auth\auth();
            $guest = $this->guest_auth->obtain_user_data(ANONYMOUS);
            $this->guest_auth->acl($guest);
        }
        return (bool) $this->guest_auth->acl_get('f_read', $forum_id);
    }

    private function public_forum($forum_id)
    {
        if ($forum_id <= 0) { return false; }
        $sql = 'SELECT forum_id FROM ' . $this->prefix . 'forums WHERE forum_id = ' . (int) $forum_id .
            " AND forum_password = '' AND forum_type = 1";
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row && $this->guest_can_read($forum_id);
    }

    private function fetch($sql, $limit = 1, $offset = 0)
    {
        $result = $this->db->sql_query_limit($sql, $limit, $offset);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);
        return $rows;
    }

    private function paging($args)
    {
        return [
            max(1, min(50, (int) ($args['limit'] ?? 20))),
            max(0, min(10000, (int) ($args['offset'] ?? 0))),
        ];
    }

    public function run($name, array $args)
    {
        list($limit, $offset) = $this->paging($args);
        switch ($name) {
            case 'forums.list':
                $items = $this->fetch('SELECT forum_id, parent_id, forum_name, forum_desc,
                    forum_topics_approved, forum_posts_approved FROM ' . $this->prefix .
                    "forums WHERE forum_type = 1 AND forum_password = '' ORDER BY left_id", 500);
                return ['items' => array_values(array_filter($items, function ($row) {
                    return $this->guest_can_read((int) $row['forum_id']);
                }))];
            case 'forums.get':
                $id = $this->required_id($args, 'forum_id');
                if (!$this->public_forum($id)) { return ['item' => null]; }
                return ['item' => $this->first($this->fetch('SELECT forum_id, parent_id,
                    forum_name, forum_desc, forum_topics_approved, forum_posts_approved FROM ' .
                    $this->prefix . 'forums WHERE forum_id = ' . $id))];
            case 'topics.list':
                $id = $this->required_id($args, 'forum_id');
                if (!$this->public_forum($id)) { return ['items' => []]; }
                return ['items' => $this->fetch('SELECT topic_id, forum_id, topic_title,
                    topic_poster, topic_time, topic_posts_approved, topic_views
                    FROM ' . $this->prefix . 'topics WHERE forum_id = ' . $id .
                    ' AND topic_visibility = 1 ORDER BY topic_last_post_time DESC', $limit, $offset)];
            case 'topics.get':
                $id = $this->required_id($args, 'topic_id');
                $row = $this->first($this->fetch('SELECT topic_id, forum_id, topic_title,
                    topic_poster, topic_time, topic_posts_approved, topic_views
                    FROM ' . $this->prefix . 'topics WHERE topic_id = ' . $id . ' AND topic_visibility = 1'));
                return ['item' => $row && $this->public_forum((int) $row['forum_id']) ? $row : null];
            case 'posts.list':
                $id = $this->required_id($args, 'topic_id');
                $topic = $this->first($this->fetch('SELECT forum_id FROM ' . $this->prefix .
                    'topics WHERE topic_id = ' . $id . ' AND topic_visibility = 1'));
                if (!$topic || !$this->public_forum((int) $topic['forum_id'])) { return ['items' => []]; }
                return ['items' => $this->fetch('SELECT post_id, topic_id, forum_id, poster_id,
                    post_subject, post_text, post_time FROM ' . $this->prefix .
                    'posts WHERE topic_id = ' . $id . ' AND post_visibility = 1 ORDER BY post_id', $limit, $offset)];
            case 'posts.get':
                $id = $this->required_id($args, 'post_id');
                $row = $this->first($this->fetch('SELECT p.post_id, p.topic_id, p.forum_id,
                    p.poster_id, p.post_subject, p.post_text, p.post_time
                    FROM ' . $this->prefix . 'posts p JOIN ' . $this->prefix .
                    'topics t ON t.topic_id = p.topic_id WHERE p.post_id = ' . $id .
                    ' AND p.post_visibility = 1 AND t.topic_visibility = 1'));
                return ['item' => $row && $this->public_forum((int) $row['forum_id']) ? $row : null];
            case 'posts.search':
                $query = trim((string) ($args['query'] ?? ''));
                if (strlen($query) < 2 || strlen($query) > 100) {
                    throw new \InvalidArgumentException('Query length must be 2-100 bytes.');
                }
                $like = $this->db->sql_like_expression($this->db->get_any_char() .
                    $this->db->sql_escape($query) . $this->db->get_any_char());
                $rows = $this->fetch('SELECT p.post_id, p.topic_id, p.forum_id,
                    p.post_subject, p.post_time FROM ' . $this->prefix .
                    'posts p JOIN ' . $this->prefix . 'topics t ON t.topic_id = p.topic_id
                    WHERE p.post_visibility = 1 AND t.topic_visibility = 1
                    AND p.post_text ' . $like . ' ORDER BY p.post_time DESC', 200);
                $public = array_values(array_filter($rows, function ($row) {
                    return $this->public_forum((int) $row['forum_id']);
                }));
                return ['items' => array_slice($public, 0, $limit)];
            case 'users.get_public':
                $id = $this->required_id($args, 'user_id');
                return ['item' => $this->first($this->fetch('SELECT user_id, username,
                    user_regdate, user_posts FROM ' . USERS_TABLE .
                    ' WHERE user_id = ' . $id . ' AND user_type IN (0, 3)'))];
        }
        throw new \InvalidArgumentException('Unknown tool.');
    }

    private function required_id($args, $key)
    {
        $value = $args[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException($key . ' must be a positive integer.');
        }
        return $value;
    }

    private function first($items)
    {
        return count($items) ? $items[0] : null;
    }
}
