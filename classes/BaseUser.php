<?php

abstract class BaseUser {
    protected int $id;
    protected string $username;
    protected string $role;
    protected string $name;
    protected string $phone;

    public function __construct(int $id, string $username, string $role, string $name, string $phone) {
        $this->id = $id;
        $this->username = $username;
        $this->role = $role;
        $this->name = $name;
        $this->phone = $phone;
    }

    // Encapsulation: Getters
    public function getId(): int {
        return $this->id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getRole(): string {
        return $this->role;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPhone(): string {
        return $this->phone;
    }

    // Encapsulation: Setters
    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setPhone(string $phone): void {
        $this->phone = $phone;
    }

    /**
     * Polymorphic method to fetch navigation actions based on user type.
     * @return array List of navigation items (label => file path)
     */
    abstract public function getNavigationMenu(): array;

    /**
     * Factory method to create role-specific user instance from database row.
     */
    public static function createFromDbRow(array $row): BaseUser {
        $id = (int)$row['id'];
        $username = $row['username'];
        $role = $row['role'];
        $name = Encryption::decrypt($row['name']) ?? 'Unknown';
        $phone = Encryption::decrypt($row['phone']) ?? '';

        switch ($role) {
            case 'SysAdmin':
                return new SysAdmin($id, $username, $name, $phone);
            case 'CEO':
                return new CEO($id, $username, $name, $phone);
            case 'Manager':
                return new Manager($id, $username, $name, $phone);
            case 'Seller':
                return new Seller($id, $username, $name, $phone);
            default:
                throw new Exception("Invalid user role: " . $role);
        }
    }

    /**
     * Retrieves the currently logged-in user object.
     */
    public static function getLoggedInUser(): ?BaseUser {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row) {
                return self::createFromDbRow($row);
            }
        } catch (Exception $e) {
            error_log("Error in getLoggedInUser: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Enforces login status, redirecting to login page if unauthenticated.
     */
    public static function requireLogin(): BaseUser {
        $user = self::getLoggedInUser();
        if (!$user) {
            header("Location: login.php");
            exit;
        }
        return $user;
    }

    /**
     * Enforces role requirements, showing a forbidden page if unauthorized.
     */
    public static function requireRole(array $allowedRoles): BaseUser {
        $user = self::requireLogin();
        if (!in_array($user->getRole(), $allowedRoles)) {
            http_response_code(403);
            echo "<!DOCTYPE html>
            <html>
            <head>
                <title>Access Forbidden</title>
                <link rel='stylesheet' href='assets/style.css'>
            </head>
            <body>
                <div class='container' style='margin-top: 100px; text-align: center;'>
                    <h1 class='header-title'>Ginna Beauty Collection</h1>
                    <div class='alert alert-danger'>
                        Access Forbidden: You do not have permission to access this page.
                    </div>
                    <p><a href='dashboard.php' class='btn'>Back to Dashboard</a></p>
                </div>
            </body>
            </html>";
            exit;
        }
        return $user;
    }
}


