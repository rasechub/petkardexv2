<?php
/**
 * PetKardex v2 - AuthController
 *
 * Maneja inicio/cierre de sesión, recuperación de contraseña y control de acceso.
 * Sin credenciales quemadas en el código fuente.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Helpers\Security;

class AuthController extends Controller
{
    private Auth $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
    }

    // ─── Login ─────────────────────────────────────────────────────────────

    /**
     * GET /app/login
     * Muestra el formulario de inicio de sesión.
     */
    public function loginPage(): void
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirect($this->getRedirectPathByRole());
        }

        $this->render('auth/login', [
            'title'     => 'Iniciar Sesión — ' . Config::APP_NAME,
            'csrfToken' => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/login
     * Procesa las credenciales enviadas (soporta formulario HTML y Fetch/AJAX JSON).
     */
    public function loginProcess(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/app/login');
        }

        $this->requireValidCsrf();

        $email    = (string) $this->input('email', '');
        $password = (string) ($_POST['password'] ?? '');

        $limitKey    = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $maxAttempts = Config::getMaxLoginAttempts();
        $lockWindow  = Config::getLoginLockoutSeconds();

        if (!Security::rateLimit($limitKey, $maxAttempts, $lockWindow)) {
            $msg = 'Demasiados intentos fallidos. Intenta nuevamente en unos minutos.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $msg], 429);
            }
            $this->setFlash('error', $msg);
            $this->redirect('/app/login');
        }

        if ($email === '' || $password === '') {
            $msg = 'Ingresa tu correo electrónico y contraseña.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $msg], 422);
            }
            $this->setFlash('error', $msg);
            $this->redirect('/app/login');
        }

        if (!Security::validateEmail($email)) {
            $msg = 'El formato del correo electrónico no es válido.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $msg], 422);
            }
            $this->setFlash('error', $msg);
            $this->redirect('/app/login');
        }

        $result = $this->auth->login($email, $password);

        if (!$result['success']) {
            $msg = $result['error'] ?? 'Credenciales incorrectas.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $msg], 401);
            }
            $this->setFlash('error', $msg);
            $this->redirect('/app/login');
        }

        // Login exitoso: limpiar contador de intentos fallidos
        Security::resetRateLimit($limitKey);

        $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);

        $target = ($redirectUrl && str_starts_with((string) $redirectUrl, '/app/'))
            ? (string) $redirectUrl
            : $this->getRedirectPathByRole();

        if ($this->isAjax()) {
            $this->json([
                'success'  => true,
                'redirect' => $target,
                'user'     => [
                    'id'         => $result['user']['id'],
                    'nombre'     => $result['user']['nombre'],
                    'apellido'   => $result['user']['apellido'],
                    'rol_slug'   => $result['user']['rol_slug'],
                    'rol_nombre' => $result['user']['rol_nombre'],
                ],
            ]);
        }

        $this->redirect($target);
    }

    /**
     * GET|POST /app/logout
     * Cierra la sesión de forma segura y redirige.
     */
    public function logout(): void
    {
        $this->auth->logout();

        if ($this->isAjax()) {
            $this->json(['success' => true, 'redirect' => '/app/login']);
        }

        $this->redirect('/app/login?logout=1');
    }

    /**
     * GET /app/auth/me
     * Retorna el usuario autenticado actual en formato JSON.
     */
    public function me(): void
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(['success' => false, 'error' => 'No autenticado.'], 401);
        }

        $this->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    /**
     * GET /app/unauthorized
     * Muestra la respuesta cuando el rol no tiene acceso al módulo solicitado.
     */
    public function unauthorized(): void
    {
        http_response_code(403);

        if ($this->isAjax()) {
            $this->json(['success' => false, 'error' => 'No tienes permisos para acceder a este recurso.'], 403);
        }

        $view403 = $this->viewsPath . 'errors/403.php';
        if (file_exists($view403)) {
            $this->render('errors/403', ['title' => 'Acceso Denegado — ' . Config::APP_NAME]);
            return;
        }

        echo '<!DOCTYPE html><html lang="es"><body><h1>403 — Acceso Denegado</h1>'
           . '<p>Tu rol actual no tiene permisos para acceder a esta sección.</p>'
           . '<p><a href="' . Security::e($this->getRedirectPathByRole()) . '">Volver a mi panel</a></p>'
           . '</body></html>';
    }

    // ─── Recuperación de contraseña ─────────────────────────────────────────

    /**
     * GET  /app/forgot-password — Muestra el formulario
     * POST /app/forgot-password — Procesa la solicitud
     */
    public function forgotPassword(): void
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirect($this->getRedirectPathByRole());
        }

        if ($this->isPost()) {
            $this->requireValidCsrf();

            $email = strtolower((string) $this->input('email', ''));

            if (!Security::validateEmail($email)) {
                $msg = 'Ingresa un correo electrónico válido.';
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => $msg], 422);
                }
                $this->setFlash('error', $msg);
                $this->redirect('/app/forgot-password');
            }

            $limitKey = 'forgot_' . hash('sha256', $email);
            if (!Security::rateLimit($limitKey, 3, 900)) {
                $msg = 'Demasiadas solicitudes. Intenta más tarde.';
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => $msg], 429);
                }
                $this->setFlash('error', $msg);
                $this->redirect('/app/forgot-password');
            }

            $result = $this->auth->generatePasswordResetToken($email);

            if ($result['success'] && isset($result['token'], $result['user'])) {
                $this->sendPasswordResetEmail(
                    $result['user']['email'],
                    $result['user']['nombre'],
                    $result['token']
                );
            }

            // Mensaje genérico para evitar enumeración de correos
            $okMsg = 'Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña.';
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => $okMsg]);
            }
            $this->setFlash('success', $okMsg);
            $this->redirect('/app/forgot-password');
        }

        $this->render('auth/forgot-password', [
            'title'     => 'Recuperar Contraseña — ' . Config::APP_NAME,
            'csrfToken' => $this->generateToken(),
        ]);
    }

    /**
     * GET  /app/reset-password/{token} — Muestra el formulario de nueva contraseña
     * POST /app/reset-password/{token} — Procesa el cambio
     */
    public function resetPassword(string $token = ''): void
    {
        if ($this->auth->isAuthenticated()) {
            $this->redirect($this->getRedirectPathByRole());
        }

        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            $this->setFlash('error', 'El enlace de recuperación no es válido.');
            $this->redirect('/app/login');
        }

        if ($this->isPost()) {
            $this->requireValidCsrf();

            $newPassword     = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

            $passwordErrors = Security::validatePassword($newPassword);
            if (!empty($passwordErrors)) {
                $msg = implode(' ', $passwordErrors);
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => $msg], 422);
                }
                $this->setFlash('error', $msg);
                $this->redirect("/app/reset-password/{$token}");
            }

            if ($newPassword !== $confirmPassword) {
                $msg = 'Las contraseñas no coinciden.';
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => $msg], 422);
                }
                $this->setFlash('error', $msg);
                $this->redirect("/app/reset-password/{$token}");
            }

            $result = $this->auth->resetPassword($token, $newPassword);

            if (!$result['success']) {
                $msg = $result['error'] ?? 'No se pudo restablecer la contraseña.';
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => $msg], 400);
                }
                $this->setFlash('error', $msg);
                $this->redirect("/app/reset-password/{$token}");
            }

            $okMsg = 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.';
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => $okMsg, 'redirect' => '/app/login']);
            }
            $this->setFlash('success', $okMsg);
            $this->redirect('/app/login');
        }

        $this->render('auth/reset-password', [
            'title'     => 'Nueva Contraseña — ' . Config::APP_NAME,
            'token'     => $token,
            'csrfToken' => $this->generateToken(),
        ]);
    }

    // ─── Helpers internos ───────────────────────────────────────────────────

    /**
     * Retorna la ruta de inicio según el rol del usuario autenticado.
     * Todos los roles aterrizan en el Dashboard condicional (/app/dashboard).
     */
    private function getRedirectPathByRole(): string
    {
        $rol = $_SESSION['user']['rol_slug'] ?? 'propietario';

        $routes = [
            'superadmin'    => '/app/dashboard',
            'veterinario'   => '/app/dashboard',
            'guardia'       => '/app/dashboard',
            'recepcionista' => '/app/dashboard',
            'groomer'       => '/app/dashboard',
            'propietario'   => '/app/dashboard',
            'emergencia'    => '/app/dashboard',
        ];

        return $routes[$rol] ?? '/app/dashboard';
    }

    /**
     * Envía el email de recuperación de contraseña (pendiente de integración PHPMailer/SMTP).
     */
    private function sendPasswordResetEmail(string $to, string $nombre, string $token): void
    {
        $baseUrl = Config::getBaseUrl();
        $link    = "{$baseUrl}/app/reset-password/{$token}";

        if (!Config::isProduction()) {
            error_log("[PetKardex] Password reset para {$to} ({$nombre}): {$link}");
        }
    }
}
