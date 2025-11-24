<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * JSON file read/write utilities (à changer plus tard pour la base de données)
 */
function load_json(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): void
{
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * User-related functions
 */
/*function load_users(): array
{
    return load_json(USERS_FILE);
}

function save_users(array $users): void
{
    save_json(USERS_FILE, $users);
}*/

/*function find_user_by_email(string $email): ?array
{
    $users = load_users();
    $email = strtolower(trim($email));
    foreach ($users as $user) {
        if (strtolower($user['email']) === $email) {
            return $user;
        }
    }
    return null;
}*/

function find_user_by_email(string $email): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    
    $user = $stmt->fetch();
    return $user ?: null;
}

/*function create_user(string $name, string $email, string $password): array
{
    $users = load_users();

    if (find_user_by_email($email)) {
        throw new Exception("Cet email est déjà utilisé.");
    }

    $user = [
        'id' => uniqid('u_', true),
        'name' => trim($name),
        'email' => strtolower(trim($email)),
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ];

    $users[] = $user;
    save_users($users);

    return $user;
}*/

function create_user(string $name, string $email, string $password): array
{
    global $pdo;

    if (find_user_by_email($email)) {
        throw new Exception("Cet email est déjà utilisé.");
    }

    $emailClean = strtolower(trim($email));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (nom, email, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $emailClean, $passwordHash]);

    return [
        'id' => $pdo->lastInsertId(),
        'nom' => $name,
        'email' => $emailClean
    ];
}

function authenticate(string $email, string $password): ?array
{
    $user = find_user_by_email($email);
    if (!$user) {
        return null;
    }
    if (!password_verify($password, $user['password'])) {
        return null;
    }
    return $user;
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['nom'],
        'email' => $user['email'],
    ];
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Project-related functions (à changer plus tard pour la base de données)
 */
function load_projects(): array
{
    return load_json(PROJECTS_FILE);
}

function save_projects(array $projects): void
{
    save_json(PROJECTS_FILE, $projects);
}

function generate_id(string $prefix = ''): string
{
    return uniqid($prefix, true);
}

/**
 * Get human-readable status label
 */
function status_label(string $status): string
{
    switch ($status) {
        case 'in_progress':
            return 'En cours';
        case 'done':
            return 'Terminé';
        case 'todo':
        default:
            return 'À faire';
    }
}
