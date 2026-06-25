<?php

class Seller extends BaseUser {
    public function __construct(int $id, string $username, string $name, string $phone) {
        parent::__construct($id, $username, 'Seller', $name, $phone);
    }

    public function getNavigationMenu(): array {
        return [
            'Create Sale (POS)' => 'sales.php'
        ];
    }
}
