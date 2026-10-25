<?php
namespace topofgames\phpbbmcp\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\topofgames\phpbbmcp\acp\main_module',
            'title' => 'ACP_PHPBBMCP_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'ACP_PHPBBMCP_TITLE',
                    'auth' => 'ext_topofgames/phpbbmcp && acl_a_board',
                    'cat' => ['ACP_PHPBBMCP_TITLE'],
                ],
            ],
        ];
    }
}
