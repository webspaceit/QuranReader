<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Auth\Auth;
use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\BookmarkRepository;
use Quran\Repositories\SettingsRepository;
use Quran\Repositories\UserRepository;
use Quran\Support\Config;
use Quran\Support\Csrf;
use Quran\Support\Logger;
use Quran\Support\Mailer;
use Quran\Support\RateLimiter;
use Quran\Support\Validator;

/**
 * Registration, login, logout and password reset.
 *
 * Error messages for authentication attempts are intentionally generic so that
 * the API never reveals whether an email address is registered.
 */
final class AuthController
{
    public function register(): array
    {
        $this->throttle('register_ip', Request::ip(), 5, 3600);

        $input = Request::all();
        $v     = Validator::make($input)
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->max('email', 254, 'Email')
            ->required('password', 'Password')
            ->max('password', 200, 'Password')
            ->max('full_name', 120, 'Name')
            ->confirm('password', 'password_confirmation', 'Password confirmation');

        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $email    = UserRepository::normalizeEmail((string) $v->value('email'));
        $password = (string) $v->value('password');

        $issues = Auth::passwordIssues($password);
        if ($issues !== []) {
            throw ApiException::invalid($issues);
        }

        $result = Auth::register($email, $password, $v->value('full_name'));
        if ($result === null) {
            // Do not confirm that the address is taken.
            throw ApiException::invalid(
                ['email' => 'That email address cannot be used. Try signing in instead.'],
                'We could not create an account with those details.'
            );
        }

        Auth::login($result['id']);
        RateLimiter::clear('login_email', $email . '|' . Request::ip());

        return [
            'user'       => $this->presentUser(UserRepository::findById($result['id'])),
            'settings'   => Auth::settings(),
            'csrf_token' => Csrf::token(),
        ];
    }

    public function login(): array
    {
        $input = Request::all();
        $v     = Validator::make($input)
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password');

        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $email    = UserRepository::normalizeEmail((string) $v->value('email'));
        $password = (string) $v->value('password');

        $ip    = Request::ip();
        $perIp = RateLimiter::hit('login_ip', $ip, 25, 900);
        if (!$perIp['allowed']) {
            throw ApiException::tooManyRequests($perIp['retry_after']);
        }

        $perAccount = RateLimiter::hit('login_email', $email . '|' . $ip, 6, 900);
        if (!$perAccount['allowed']) {
            Logger::warning('Login throttled', ['email_hash' => hash('sha256', $email), 'ip' => $ip]);
            throw ApiException::tooManyRequests($perAccount['retry_after']);
        }

        $user = Auth::attempt($email, $password);
        if ($user === null) {
            throw new ApiException('Incorrect email or password.', 401, 'invalid_credentials');
        }

        Auth::login((int) $user['id']);
        RateLimiter::clear('login_email', $email . '|' . $ip);

        return [
            'user'       => $this->presentUser(UserRepository::findById((int) $user['id'])),
            'settings'   => Auth::settings(),
            'progress'   => Auth::progress(),
            'bookmarks'  => BookmarkRepository::countForUser((int) $user['id']),
            'csrf_token' => Csrf::token(),
        ];
    }

    public function logout(): array
    {
        Auth::logout();
        return ['message' => 'Signed out.', 'csrf_token' => Csrf::token()];
    }

    /**
     * Always answer with the same message so the endpoint cannot be used to
     * enumerate registered addresses.
     */
    public function forgotPassword(): array
    {
        $input = Request::all();
        $v     = Validator::make($input)->required('email', 'Email')->email('email', 'Email');
        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $email = UserRepository::normalizeEmail((string) $v->value('email'));

        $ipBucket = RateLimiter::hit('forgot_ip', Request::ip(), 10, 3600);
        if (!$ipBucket['allowed']) {
            throw ApiException::tooManyRequests($ipBucket['retry_after']);
        }
        $mailBucket = RateLimiter::hit('forgot_email', $email, 3, 900);
        if (!$mailBucket['allowed']) {
            throw ApiException::tooManyRequests($mailBucket['retry_after']);
        }

        $user = UserRepository::findByEmail($email);
        if ($user !== null) {
            $token = Auth::createResetToken((int) $user['id'], 60);
            $link  = absolute_url('reset-password.php?token=' . urlencode($token));

            $html = view('emails/password-reset', [
                'name'      => (string) ($user['full_name'] ?? ''),
                'resetUrl'  => $link,
                'appName'   => (string) Config::get('app.name', 'Quran Reader'),
                'expiresIn' => '60 minutes',
            ]);
            $text = "Reset your password\n\n"
                . $link . "\n\n"
                . "This link expires in 60 minutes. If you did not request it, ignore this email.";

            if (!Mailer::send($email, 'Reset your password', $html, $text)) {
                Logger::warning('Password reset email could not be sent', ['email_hash' => hash('sha256', $email)]);
            }
        } else {
            // Burn the same amount of time as a real request.
            usleep(random_int(120000, 260000));
        }

        return [
            'message' => 'If that email is registered, a reset link is on its way. Please check your inbox and spam folder.',
        ];
    }

    public function resetPassword(): array
    {
        $b1 = RateLimiter::hit('reset_ip', Request::ip(), 10, 900);
        if (!$b1['allowed']) {
            throw ApiException::tooManyRequests($b1['retry_after']);
        }

        $input = Request::all();
        $v     = Validator::make($input)
            ->required('token', 'Reset token')
            ->required('password', 'Password')
            ->max('password', 200, 'Password')
            ->confirm('password', 'password_confirmation', 'Password confirmation');

        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $password = (string) $v->value('password');
        $issues   = Auth::passwordIssues($password);
        if ($issues !== []) {
            throw ApiException::invalid($issues);
        }

        $token = (string) $v->value('token');
        if (!Auth::resetPassword($token, $password)) {
            throw new ApiException(
                'This reset link is invalid or has expired. Please request a new one.',
                422,
                'invalid_token'
            );
        }

        return ['message' => 'Your password has been updated. You can sign in now.'];
    }

    public function me(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw ApiException::unauthorized();
        }

        return [
            'user'          => $this->presentUser($user),
            'settings'      => Auth::settings(),
            'progress'      => Auth::progress(),
            'bookmark_count'=> BookmarkRepository::countForUser((int) $user['id']),
            'csrf_token'    => Csrf::token(),
        ];
    }

    public function updateProfile(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw ApiException::unauthorized();
        }

        $v = Validator::make(Request::all())->max('full_name', 120, 'Name');
        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $name = $v->value('full_name');
        UserRepository::updateProfile((int) $user['id'], $name === '' ? null : $name);

        return ['user' => $this->presentUser(UserRepository::findById((int) $user['id']))];
    }

    public function changePassword(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw ApiException::unauthorized();
        }

        $bucket = RateLimiter::hit('password_change', (string) $user['id'], 5, 3600);
        if (!$bucket['allowed']) {
            throw ApiException::tooManyRequests($bucket['retry_after']);
        }

        $v = Validator::make(Request::all())
            ->required('current_password', 'Current password')
            ->required('password', 'New password')
            ->max('password', 200, 'New password')
            ->confirm('password', 'password_confirmation', 'Password confirmation');

        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $full = UserRepository::findByEmail((string) $user['email']);
        if ($full === null || !password_verify((string) $v->value('current_password'), (string) $full['password_hash'])) {
            throw ApiException::invalid(['current_password' => 'Your current password is not correct.']);
        }

        $newPassword = (string) $v->value('password');
        $issues      = Auth::passwordIssues($newPassword);
        if ($issues !== []) {
            throw ApiException::invalid($issues);
        }

        UserRepository::updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        Logger::info('Password changed', ['user_id' => (int) $user['id']]);

        return ['message' => 'Password updated.'];
    }

    /** Delete the account and all attached data (GDPR-style). */
    public function deleteAccount(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw ApiException::unauthorized();
        }

        $v = Validator::make(Request::all())->required('password', 'Password');
        if ($v->fails()) {
            throw ApiException::invalid($v->errors());
        }

        $full = UserRepository::findByEmail((string) $user['email']);
        if ($full === null || !password_verify((string) $v->value('password'), (string) $full['password_hash'])) {
            throw ApiException::invalid(['password' => 'Password is not correct.']);
        }

        UserRepository::delete((int) $user['id']);
        Auth::logout();

        return ['message' => 'Your account and all saved data have been deleted.'];
    }

    /** @param array<string,mixed>|null $user */
    private function presentUser(?array $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            'id'         => (int) $user['id'],
            'email'      => (string) $user['email'],
            'full_name'  => $user['full_name'] ?? null,
            'created_at' => $user['created_at'] ?? null,
        ];
    }

    private function throttle(string $bucket, string $identifier, int $max, int $window): void
    {
        $result = RateLimiter::hit($bucket, $identifier, $max, $window);
        if (!$result['allowed']) {
            throw ApiException::tooManyRequests($result['retry_after']);
        }
    }
}
