<section class="section auth-section">
    <div class="container auth-container">
        <div class="card auth-card">
            <h1>Reset your password</h1>
            <p class="muted">Enter the email address you registered with and we will send a reset link.</p>

            <form class="auth-form" data-auth-form="forgot" data-api="<?= e(u('api/auth/forgot-password.php')) ?>">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required autofocus>
                </div>

                <div class="form-alert" data-form-error hidden role="alert"></div>
                <div class="form-alert alert-success" data-form-success hidden role="status"></div>

                <button type="submit" class="btn btn-primary btn-block">Send reset link</button>

                <div class="auth-links">
                    <a href="<?= e(u('login.php')) ?>">Back to sign in</a>
                </div>
            </form>
        </div>

        <p class="auth-note muted small">
            For privacy we always show the same confirmation, whether or not the address is registered.
            The link expires after 60 minutes and can only be used once.
        </p>
    </div>
</section>
