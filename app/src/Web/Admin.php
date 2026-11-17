<?php
declare(strict_types=1);

namespace Quran\Web;

use Quran\Auth\Auth;
use Quran\Repositories\SiteSettingsRepository;
use Quran\Support\Csrf;
use Quran\Support\Session;

/**
 * Super admin dashboard.
 *
 * Only the installation owner reaches these pages: the account carries the
 * is_admin flag, or its email is nominated in app.admin_email. Everyone else
 * gets a 403 without learning what is behind it.
 */
final class Admin
{
    public function dashboard(): void
    {
        if (!$this->guard()) {
            return;
        }

        $this->render('admin', [
            'pageTitle' => 'Site admin — ' . (string) \Quran\Support\Config::get('app.name', 'Quran Reader'),
            'activeNav' => 'admin',
            'site'      => SiteSettingsRepository::all(),
            'year'      => SiteSettingsRepository::copyrightYear(),
        ]);
    }

    /** POST target for the footer form. Always redirects (post/redirect/get). */
    public function saveFooter(): void
    {
        if (!$this->guard()) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Your session expired. Please try again.');
            $this->redirect('admin.php');
            return;
        }

        $input = is_array($_POST) ? $_POST : [];

        $tagline = SiteSettingsRepository::normalise((string) ($input['footer_tagline'] ?? ''));
        $holder  = SiteSettingsRepository::normaliseHolder((string) ($input['footer_copyright_holder'] ?? ''));
        $note    = SiteSettingsRepository::normalise((string) ($input['footer_note'] ?? ''));
        $year    = trim((string) ($input['footer_copyright_year'] ?? 'auto'));

        $errors = [];
        if ($tagline === '' || mb_strlen($tagline) > 240) {
            $errors[] = 'The tagline must be between 1 and 240 characters.';
        }
        if ($holder === '' || mb_strlen($holder) > 120) {
            $errors[] = 'The copyright holder must be between 1 and 120 characters.';
        }
        if (mb_strlen($note) > 160) {
            $errors[] = 'The extra footer line is limited to 160 characters.';
        }
        if ($year !== 'auto' && preg_match('/^\d{4}$/', $year) !== 1) {
            $errors[] = 'The copyright year must be "auto" or a four-digit year.';
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('admin.php');
            return;
        }

        SiteSettingsRepository::put([
            'footer_tagline'          => $tagline,
            'footer_copyright_holder' => $holder,
            'footer_note'             => $note,
            'footer_copyright_year'   => $year,
        ], Auth::id());

        Session::flash('status', 'Footer updated. The change is live on every page.');
        $this->redirect('admin.php');
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    private function guard(): bool
    {
        if (!Auth::check()) {
            Session::flash('error', 'Sign in with the owner account to reach the admin dashboard.');
            $this->redirect('login.php');
            return false;
        }

        if (!Auth::isAdmin()) {
            http_response_code(403);
            (new Site())->forbidden('This area is reserved for the site owner.');
            return false;
        }

        return true;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . u($path), true, 302);
        exit;
    }

    /** @param array<string,mixed> $extra */
    private function render(string $template, array $extra): void
    {
        $data = [
            'appName'         => (string) \Quran\Support\Config::get('app.name', 'Quran Reader'),
            'metaDescription' => 'Site administration.',
            'canonical'       => absolute_url('admin.php'),
            'currentUser'     => Auth::user(),
            'settings'        => Auth::settings(),
            'csrfToken'       => Csrf::token(),
            'bookmarkCount'   => 0,
            'flashStatus'     => Session::flash('status'),
            'flashError'      => Session::flash('error'),
            'activeNav'       => '',
            'bodyClass'       => 'page-admin',
            'pageScripts'     => [],
            'pageStyles'      => [],
            'site'            => SiteSettingsRepository::all(),
        ];

        render_page($template, array_merge($data, $extra));
    }
}
