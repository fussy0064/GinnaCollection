<?php

class CEO extends BaseUser {
    public function __construct(int $id, string $username, string $name, string $phone) {
        parent::__construct($id, $username, 'CEO', $name, $phone);
    }

    public function getNavigationMenu(): array {
        return [
            'Sales & Revenue Reports' => 'reports.php'
        ];
    }
}
