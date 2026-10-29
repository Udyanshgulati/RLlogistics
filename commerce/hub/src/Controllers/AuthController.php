<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;
use Commerce\Hub\RateLimiter;

final class AuthController extends Controller
{
    public const MIN_PASSWORD = 12;
    private static ?string $dummyHash = null;

    public function loginForm(Request $req): Response
    {
        if ($this->app->session->get('user_id')) {
            return Response::redirect('/');
        }
        return $this->app->page($req, 'login', ['bare' => true, 'next' => $req->q('next', '/'), 'email' => '']);
    }

    public function login(Request $req): Response
    {
        $email = strtolower($req->p('email'));
        $next = $req->p('next', '/');
        $limiter = new RateLimiter($this->db, $this->app->clock);
        $fail = fn (string $msg) => $this->app->page($req, 'login', ['bare' => true, 'next' => $next, 'email' => $email, 'error' => $msg], 401);
        // Only failed attempts count towards the limits, so people who sign in often are never locked out.
        if ($limiter->exceeded('login:ip:' . $req->ip, 20, 900) || $limiter->exceeded('login:email:' . $email, 5, 900)) {
            return $fail('Too many sign-in attempts. Wait 15 minutes and try again.');
        }
        $fail = function (string $msg) use ($fail, $limiter, $req, $email) {
            $limiter->hit('login:ip:' . $req->ip, 20, 900);
            $limiter->hit('login:email:' . $email, 5, 900);
            return $fail($msg);
        };
        $u = $this->db->one('SELECT * FROM users WHERE email = ?', [$email]);
        $now = $this->app->clock->now();
        if ($u !== null && $u['locked_until'] !== null && new \DateTimeImmutable($u['locked_until'], new \DateTimeZone('UTC')) > $now) {
            return $fail('This account is temporarily locked after repeated failed attempts. Try again later.');
        }
        // Same work and the same message whether or not the account exists.
        $hash = $u['password_hash'] ?? (self::$dummyHash ??= password_hash(bin2hex(random_bytes(16)), self::algo()));
        if (!password_verify($req->p('password'), $hash) || $u === null || $u['status'] !== 'active') {
            if ($u !== null) {
                $failed = (int) $u['failed_logins'] + 1;
                $this->db->run('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?', [$failed, $failed >= 10 ? $now->modify('+15 minutes') : null, $u['id']]);
            }
            return $fail('The email or password is not correct.');
        }
        if (password_needs_rehash($u['password_hash'], self::algo())) {
            $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($req->p('password'), self::algo()), $u['id']]);
        }
        $this->db->run('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = ? WHERE id = ?', [$now, $u['id']]);
        $this->signIn((int) $u['id'], $req->ip);
        return Response::redirect($next);
    }

    public function logout(Request $req): Response
    {
        $this->app->session->destroy();
        return Response::redirect('/login');
    }

    public function setupForm(Request $req): Response
    {
        $u = $this->userForToken($req->q('token'));
        return $this->app->page($req, 'setup', ['bare' => true, 'token' => $req->q('token'), 'user' => $u, 'error' => $u ? null : 'This link has expired or was already used. Ask an owner for a new one.']);
    }

    public function setup(Request $req): Response
    {
        $token = $req->p('token');
        $u = $this->userForToken($token);
        $pw = $req->p('password');
        $error = match (true) {
            $u === null => 'This link has expired or was already used. Ask an owner for a new one.',
            mb_strlen($pw) < self::MIN_PASSWORD => 'Use at least ' . self::MIN_PASSWORD . ' characters.',
            $pw !== $req->p('password_confirm') => 'The two passwords do not match.',
            default => null,
        };
        if ($error !== null) {
            return $this->app->page($req, 'setup', ['bare' => true, 'token' => $token, 'user' => $u, 'error' => $error], 422);
        }
        $this->db->run('UPDATE users SET password_hash = ?, setup_token_hash = NULL, setup_expires_at = NULL, failed_logins = 0 WHERE id = ?', [password_hash($pw, self::algo()), $u['id']]);
        $this->app->services->audit()->log('user:' . $u['id'], 'user.password_set', 'user', $u['id'], [], $req->ip);
        $this->signIn((int) $u['id'], $req->ip);
        return Response::redirect('/');
    }

    public static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private function signIn(int $userId, string $ip): void
    {
        $this->app->session->regenerate();
        $this->app->session->set('user_id', $userId);
        $this->app->session->set('csrf', bin2hex(random_bytes(32)));
        $this->app->services->audit()->log('user:' . $userId, 'user.login', 'user', $userId, [], $ip);
    }

    private function userForToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->db->one("SELECT * FROM users WHERE setup_token_hash = ? AND setup_expires_at > ? AND status = 'active'", [hash('sha256', $token), $this->app->clock->now()]);
    }
}
