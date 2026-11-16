<?php
/** @var string $redirect */
$redirect = $redirect ?? '';
?>
<section class="section auth-section">
    <div class="container auth-container">
        <div class="card auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to sync your bookmarks and reading position.</p>

            <form class="auth-form" data-auth-form="login" data-api="<?= e(u('api/auth/login.php')) ?>"
                  data-redirect="<?= e($redirect) ?>">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <div class="form-alert" data-form-error hidden role="alert"></div>

                <button type="submit" class="btn btn-primary btn-block">Sign in</button>

                <div class="auth-links">
                    <a href="<?= e(u('forgot-password.php')) ?>">Forgot your password?</a>
                    <a href="<?= e(u('register.php')) ?>">Create an account</a>
                </div>
            </form>
        </div>

        <p class="auth-note muted small">
            Sessions are protected with HTTP-only cookies. We never store your password — only a one-way hash.
        </p>
    </div>
</section>
