<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Validator;
use App\Repositories\UserRepository;

final class AuthService
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function register(array $input, string $clientIp): array
    {
        $validator = new Validator();
        $nombre    = $validator->requiredString($input, 'nombre', 2, 80);
        $apellidos = $validator->requiredString($input, 'apellidos', 2, 100);
        $email     = $validator->email($input, 'email');
        $password  = $validator->password($input, 'password', 8, 72);
        $telefono  = $validator->optionalString($input, 'telefono', 25);
        $direccion = $validator->optionalString($input, 'direccion', 255);
        $validator->failIfErrors();

        if ($this->users->emailExists($email)) {
            throw new HttpException(409, 'Ya existe una cuenta registrada con ese correo electrónico.', 'EMAIL_ALREADY_EXISTS');
        }

        $user = $this->users->createClient([
            'nombre'        => $nombre,
            'apellidos'     => $apellidos,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'telefono'      => $telefono,
            'direccion'     => $direccion,
        ]);

        $csrfToken = $this->startUserSession($user->id, $user->rolNombre);

        Logger::info('Nuevo cliente registrado', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $clientIp,
        ]);

        return [
            'user'       => $user->toPublicArray(),
            'csrf_token' => $csrfToken,
        ];
    }

    public function login(array $input, string $clientIp): array
    {
        $validator = new Validator();
        $email     = $validator->email($input, 'email');
        $password  = $validator->password($input, 'password', 1, 72);
        $validator->failIfErrors();

        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->activo || !password_verify($password, $user->passwordHash)) {
            Logger::warning('Intento de inicio de sesión fallido', [
                'email' => $email,
                'ip'    => $clientIp,
            ]);
            throw new HttpException(401, 'Credenciales incorrectas.', 'INVALID_CREDENTIALS');
        }

        $csrfToken = $this->startUserSession($user->id, $user->rolNombre);

        Logger::info('Inicio de sesión correcto', [
            'user_id' => $user->id,
            'rol'     => $user->rolNombre,
            'ip'      => $clientIp,
        ]);

        return [
            'user'       => $user->toPublicArray(),
            'csrf_token' => $csrfToken,
        ];
    }

    public function logout(int $userId, string $clientIp): void
    {
        Logger::info('Cierre de sesión', [
            'user_id' => $userId,
            'ip'      => $clientIp,
        ]);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public function getCurrentSessionData(array $authenticatedUser): array
    {
        $csrfToken = $this->ensureCsrfToken();

        return [
            'user'       => $authenticatedUser,
            'csrf_token' => $csrfToken,
        ];
    }

    public function updateOwnProfile(int $userId, array $input): array
    {
        $existing = $this->users->findById($userId);
        if ($existing === null) {
            throw new HttpException(404, 'Usuario no encontrado.', 'USER_NOT_FOUND');
        }

        $validator = new Validator();
        $nombre    = $validator->requiredString($input, 'nombre', 2, 80);
        $apellidos = $validator->requiredString($input, 'apellidos', 2, 100);
        $email     = $validator->email($input, 'email');
        $telefono  = $validator->optionalString($input, 'telefono', 25);
        $direccion = $validator->optionalString($input, 'direccion', 255);

        $wantsPasswordChange = isset($input['new_password']) && $input['new_password'] !== '';
        $currentPassword = '';
        $newPassword = '';

        if ($wantsPasswordChange) {
            $currentPassword = $validator->password($input, 'current_password', 1, 72);
            $newPassword     = $validator->password($input, 'new_password', 8, 72);
        }

        $validator->failIfErrors();

        if ($this->users->emailExists($email, $userId)) {
            throw new HttpException(409, 'Ese correo electrónico ya está en uso por otra cuenta.', 'EMAIL_ALREADY_EXISTS');
        }

        if ($wantsPasswordChange && !password_verify($currentPassword, $existing->passwordHash)) {
            throw new HttpException(400, 'La contraseña actual no es correcta.', 'INVALID_CURRENT_PASSWORD');
        }

        $updated = $this->users->updateProfile($userId, [
            'nombre'    => $nombre,
            'apellidos' => $apellidos,
            'email'     => $email,
            'telefono'  => $telefono,
            'direccion' => $direccion,
        ]);

        if ($wantsPasswordChange) {
            $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
        }

        return $updated->toPublicArray();
    }

    private function startUserSession(int $userId, string $role): string
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['csrf_token'];
    }

    private function ensureCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}