<?php

class SysAdmin extends BaseUser {
    public function __construct(int $id, string $username, string $name, string $phone) {
        parent::__construct($id, $username, 'SysAdmin', $name, $phone);
    }

    public function getNavigationMenu(): array {
        return [
            'Manage Users' => 'users.php',
            'System Logs' => 'logs.php'
        ];
    }
}
