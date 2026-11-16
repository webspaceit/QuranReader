<?php
/**
 * Inner content of the password reset email. Mailer::send() wraps it in a
 * minimal HTML shell with the site name.
 *
 * @var string $name
 * @var string $resetUrl
 * @var string $appName
 * @var string $expiresIn
 */
$greeting = $name !== '' ? 'Assalamu alaikum ' . $name . ',' : 'Assalamu alaikum,';
?>
<p><?= e($greeting) ?></p>
<p>We received a request to reset the password for your <?= e($appName) ?> account.</p>
<p style="margin:24px 0">
    <a href="<?= e($resetUrl) ?>"
       style="display:inline-block;background:#0F5132;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600">
        Choose a new password
    </a>
</p>
<p style="font-size:13px;color:#4B5563">This link expires in <?= e($expiresIn) ?> and can be used once.</p>
<p style="font-size:13px;color:#4B5563;word-break:break-all">
    If the button does not work, copy this address into your browser:<br>
    <a href="<?= e($resetUrl) ?>"><?= e($resetUrl) ?></a>
</p>
