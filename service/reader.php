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
    private $guest_initialized = false;

    public function __construct($db, $table_prefix, $guest_auth = null)
    {
        $this->db = $db;
        $this->prefix = $table_prefix;
        // Optional guest ACL object makes anonymous access policy testable.
        $this->guest_auth = $guest_auth;
        $this->guest_initialized = $guest_auth !== null;
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

    private function guest_can($permission, $forum_id)
    {
        if (!$this->guest_initialized) {
            // A separate ACL is essential: never replace the active ACP user's ACL.
            $this->guest_auth = new \phpbb\auth\auth();
            $guest = $this->guest_auth->obtain_user_data(ANONYMOUS);
            if (!is_array($guest) || !isset($guest['user_id'])) {
                return false;
            }
            $this->guest_auth->acl($guest);
            $this->guest_initialized = true;
        }
        return (bool) $this->guest_auth->acl_get($permission, (int) $forum_id);
    }

    private function public_forum($forum_id)
    {
        $forum_id = (int) $forum_id;
        if ($forum_id < 1) {
            return false;
        }

        // phpBB forums use nested-set left/right positions. Check the entire
        // ancestry; a readable child must never bypass a locked parent.
        $table = $this->prefix . 'forums';
        $sql = 'SELECT ancestor.forum_id, ancestor.forum_password, ancestor.forum_type
            FROM ' . $table . ' child
            INNER JOIN ' . $table . ' ancestor
                ON ancestor.left_id <= child.left_id
                AND ancestor.right_id >= child.right_id
            WHERE child.forum_id = ' . $forum_id . ' AND child.forum_type = 1
            ORDER BY ancestor.left_id';
        $ancestors = $this->fetch($sql, 128);
        if (!$ancestors || count($ancestors) >= 128) {
            // Incomplete/unusually deep ancestry fails closed.
            return false;
        }
        foreach ($ancestors as $ancestor) {
            $id = (int) $ancestor['forum_id'];
            if ((string) $ancestor['forum_password'] !== '') {
                return false;
            }
            if (!$this->guest_can('f_list', $id)) {
                return false;
            }
        }

        return $this->guest_can('f_read', $forum_id);
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
                $visible = array_values(array_filter($items, function ($row) {
                    return $this->public_forum((int) $row['forum_id']);
                }));
                return ['items' => array_slice($visible, $offset, $limit)];
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
            case 'users.get_public':
                if (!$this->guest_can('u_viewprofile', 0)) {
                    return ['item' => null];
                }
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
