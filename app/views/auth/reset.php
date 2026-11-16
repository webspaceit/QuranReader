<?php
/**
 * @var string $token
 * @var bool $valid
 */
?>
<section class="section auth-section">
    <div class="container auth-container">
        <div class="card auth-card">
            <?php if (!$valid): ?>
                <h1>Link expired</h1>
                <p class="muted">
                    This reset link is invalid, already used, or older than 60 minutes.
                    Request a new one and it will arrive within a minute or two.
                </p>
                <a class="btn btn-primary btn-block" href="<?= e(u('forgot-password.php')) ?>">Request a new link</a>
            <?php else: ?>
                <h1>Choose a new password</h1>
                <p class="muted">Pick something long and unique. You will be signed out everywhere else.</p>

                <form class="auth-form" data-auth-form="reset" data-api="<?= e(u('api/auth/reset-password.php')) ?>"
                      data-login-url="<?= e(u('login.php')) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="field">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
                        <p class="field-hint">At least 8 characters.</p>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Repeat new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>

                    <div class="form-alert" data-form-error hidden role="alert"></div>
                    <div class="form-alert alert-success" data-form-success hidden role="status"></div>

                    <button type="submit" class="btn btn-primary btn-block">Set new password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
