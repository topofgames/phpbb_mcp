<?php
namespace topofgames\phpbbmcp\acp;

class main_module
{
    public $u_action;

    public function main($id, $mode)
    {
        global $phpbb_container, $user, $template, $request, $config;

        $user->add_lang_ext('topofgames/phpbbmcp', 'common');
        $this->tpl_name = 'acp_phpbbmcp';
        $this->page_title = 'ACP_PHPBBMCP_TITLE';

        // Only founders may create/revoke bearer tokens or activate MCP.
        if ((int) $user->data['user_type'] !== USER_FOUNDER) {
            trigger_error('Only a board founder may manage MCP credentials.' . adm_back_link($this->u_action), E_USER_WARNING);
        }

        $store = $phpbb_container->get('topofgames.phpbbmcp.key_store');
        add_form_key('topofgames_phpbbmcp_admin');
        $new_key = '';
        if ($request->is_set_post('submit')) {
            if (!check_form_key('topofgames_phpbbmcp_admin')) {
                trigger_error('FORM_INVALID' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $action = $request->variable('action', '');
            if ($action === 'settings') {
                $config->set('topofgames_phpbbmcp_enabled',
                    $request->variable('enabled', 0) ? 1 : 0);
                $raw_proxies = trim($request->variable('trusted_proxies', ''));
                $list = $raw_proxies === '' ? [] : array_map('trim', explode(',', $raw_proxies));
                if (count($list) > 16 || strlen($raw_proxies) > 1024) {
                    trigger_error('Too many trusted proxies.' . adm_back_link($this->u_action), E_USER_WARNING);
                }
                foreach ($list as $address) {
                    if (!filter_var($address, FILTER_VALIDATE_IP)) {
                        trigger_error('Invalid trusted proxy IP address.' . adm_back_link($this->u_action), E_USER_WARNING);
                    }
                }
                $config->set('topofgames_phpbbmcp_trusted_proxies', implode(',', array_unique($list)));
            } elseif ($action === 'create') {
                try {
                    $new_key = $store->issue($request->variable('label', '', true),
                        (int) $user->data['user_id'],
                        $request->variable('scopes', ['forums:read', 'topics:read']),
                        $request->variable('days', 30));
                } catch (\InvalidArgumentException $e) {
                    trigger_error($e->getMessage() . adm_back_link($this->u_action), E_USER_WARNING);
                }
            } elseif ($action === 'revoke') {
                $store->revoke($request->variable('key_id', 0));
            }
        }
        $template->assign_vars([
            'U_ACTION' => $this->u_action,
            'MCP_ENABLED' => !empty($config['topofgames_phpbbmcp_enabled']),
            'MCP_NEW_KEY' => $new_key,
            'MCP_TRUSTED_PROXIES' => $config['topofgames_phpbbmcp_trusted_proxies'] ?? '',
        ]);
        foreach ($store->list_keys() as $row) {
            $template->assign_block_vars('keys', [
                'ID' => (int) $row['key_id'],
                'LABEL' => $row['key_label'],
                'PREFIX' => $row['key_prefix'],
                'SCOPES' => $row['scope_list'],
                'EXPIRES' => $user->format_date($row['expires_at']),
                'REVOKED' => (bool) $row['revoked_at'],
            ]);
        }
    }
}
