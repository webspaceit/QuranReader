<?php ?>
<section class="section auth-section">
    <div class="container auth-container auth-container--register">
        <div class="card auth-card">
            <h1>Create an account</h1>
            <p class="muted">Free, no email confirmation required, and your bookmarks follow you between devices.</p>

            <form class="auth-form" data-auth-form="register" data-api="<?= e(u('api/auth/register.php')) ?>">
                <div class="field">
                    <label for="full_name">Name <span class="muted">(optional)</span></label>
                    <input id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name">
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    <p class="field-hint">At least 8 characters. A long passphrase is stronger than a short password.</p>
                </div>
                <div class="field">
                    <label for="password_confirmation">Repeat password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                </div>

                <div class="form-alert" data-form-error hidden role="alert"></div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>

                <div class="auth-links">
                    <span class="muted small">Already registered?</span>
                    <a href="<?= e(u('login.php')) ?>">Sign in</a>
                </div>
            </form>
        </div>

        <p class="auth-note muted small">
            By creating an account you agree that your email, bookmarks and reading position are stored in this
            site's database. You can delete everything from the account page at any time.
            Read the <a href="<?= e(u('about.php')) ?>">privacy details</a>.
        </p>
    </div>
</section>
