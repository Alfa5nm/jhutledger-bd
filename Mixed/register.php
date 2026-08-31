<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$errors = (array) ($_SESSION['register_errors'] ?? []);
$values = (array) ($_SESSION['register_values'] ?? [
    'name' => '', 'email' => '', 'phone' => '', 'street' => '',
    'city' => '', 'district' => '', 'postal_code' => '', 'role' => '',
]);
unset($_SESSION['register_errors'], $_SESSION['register_values']);

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow auth-shell">
    <div class="panel">
        <div class="eyebrow">Join JhutLedger</div>
        <h1 class="h2 mt-2">Create your account</h1>
        <p class="muted">Choose the account type that matches how you use the marketplace.</p>
        <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('Mixed/actions/register.php')) ?>" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="full">
                    <label class="form-label" for="name">Full name</label>
                    <input class="form-control" id="name" name="name" maxlength="120" required value="<?= e($values['name']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input
                        class="form-control"
                        type="email"
                        id="email"
                        name="email"
                        maxlength="190"
                        required
                        value="<?= e($values['email']) ?>"
                    />
                </div>
                <div>
                    <label class="form-label" for="phone">Phone</label>
                    <input
                        class="form-control"
                        id="phone"
                        name="phone"
                        maxlength="30"
                        required
                        value="<?= e($values['phone']) ?>"
                    />
                </div>
                <div>
                    <label class="form-label" for="role">Account role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Choose role</option>
                        <option value="supplier" <?= $values['role'] === 'supplier' ? 'selected' : '' ?>>Supplier</option>
                        <option value="b2b" <?= $values['role'] === 'b2b' ? 'selected' : '' ?>>B2B Buyer</option>
                        <option value="b2c" <?= $values['role'] === 'b2c' ? 'selected' : '' ?>>B2C Buyer</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="street">Street</label>
                    <input
                        class="form-control"
                        id="street"
                        name="street"
                        maxlength="180"
                        required
                        value="<?= e($values['street']) ?>"
                    />
                </div>
                <div>
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" maxlength="100" required value="<?= e($values['city']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="district">District</label>
                    <input
                        class="form-control"
                        id="district"
                        name="district"
                        maxlength="100"
                        required
                        value="<?= e($values['district']) ?>"
                    />
                </div>
                <div>
                    <label class="form-label" for="postal_code">Postal code</label>
                    <input
                        class="form-control"
                        id="postal_code"
                        name="postal_code"
                        maxlength="20"
                        required
                        value="<?= e($values['postal_code']) ?>"
                    />
                </div>
                <div>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" minlength="8" required />
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input
                        class="form-control"
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        minlength="8"
                        required
                    />
                </div>
                <div class="full">
                    <button class="btn btn-primary w-100" type="submit">Create account</button>
                </div>
            </div>
        </form>
        <p class="text-center mt-3 mb-0">Already registered? <a href="<?= e(url('Mixed/login.php')) ?>">Log in</a></p>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
