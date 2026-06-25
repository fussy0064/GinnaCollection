<?php

class Manager extends BaseUser {
    public function __construct(int $id, string $username, string $name, string $phone) {
        parent::__construct($id, $username, 'Manager', $name, $phone);
    }

    public function getNavigationMenu(): array {
        return [
            'Manage Products' => 'products.php'
        ];
    }
}
