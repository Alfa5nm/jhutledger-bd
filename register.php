<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$errors = [];
$values = [
    'name' => '', 'email' => '', 'phone' => '', 'street' => '',
    'city' => '', 'district' => '', 'postal_code' => '', 'role' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (array_keys($values) as $key) {
        $values[$key] = input($key);
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+ -]{7,30}$/', $values['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }
    foreach (['street', 'city', 'district', 'postal_code'] as $field) {
        if ($values[$field] === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    if (!in_array($values['role'], ['supplier', 'b2b', 'b2c'], true)) {
        $errors[] = 'Select a valid account role.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO users (name, email, phone, password_hash, street, city, district, postal_code, user_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'Active\')'
            );
            $statement->execute([
                $values['name'], strtolower($values['email']), $values['phone'], password_hash($password, PASSWORD_DEFAULT),
                $values['street'], $values['city'], $values['district'], $values['postal_code'],
            ]);
            $userId = (int) $pdo->lastInsertId();
            $subtypeTable = match ($values['role']) {
                'supplier' => 'supplier',
                'b2b' => 'b2b_buyer',
                'b2c' => 'b2c_buyer',
            };
            $pdo->prepare("INSERT INTO {$subtypeTable} (user_id) VALUES (?)")->execute([$userId]);
            $pdo->commit();

            setFlash('success', "Signup complete. Database user #{$userId} and its " . formatRole($values['role']) . ' specialization were committed together.');
            redirect('login.php');
        } catch (PDOException $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getCode() === '23000'
                ? 'That email address is already registered.'
                : 'Registration could not reach the database. Please try again.';
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow auth-shell">
    <div class="panel">
        <div class="eyebrow">Transactional signup</div>
        <h1 class="h2 mt-2">Create your account</h1>
        <p class="muted">Your base user and selected role are saved together—or neither is saved.</p>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><strong>Please correct the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="full"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= e($values['name']) ?>"></div>
                <div><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" maxlength="190" required value="<?= e($values['email']) ?>"></div>
                <div><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" maxlength="30" required value="<?= e($values['phone']) ?>"></div>
                <div><label class="form-label" for="role">Account role</label><select class="form-select" id="role" name="role" required><option value="">Choose role</option><option value="supplier" <?= $values['role'] === 'supplier' ? 'selected' : '' ?>>Supplier</option><option value="b2b" <?= $values['role'] === 'b2b' ? 'selected' : '' ?>>B2B Buyer</option><option value="b2c" <?= $values['role'] === 'b2c' ? 'selected' : '' ?>>B2C Buyer</option></select></div>
                <div><label class="form-label" for="street">Street</label><input class="form-control" id="street" name="street" maxlength="180" required value="<?= e($values['street']) ?>"></div>
                <div><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" maxlength="100" required value="<?= e($values['city']) ?>"></div>
                <div><label class="form-label" for="district">District</label><input class="form-control" id="district" name="district" maxlength="100" required value="<?= e($values['district']) ?>"></div>
                <div><label class="form-label" for="postal_code">Postal code</label><input class="form-control" id="postal_code" name="postal_code" maxlength="20" required value="<?= e($values['postal_code']) ?>"></div>
                <div><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" minlength="8" required></div>
                <div><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" required></div>
                <div class="full"><button class="btn btn-primary w-100" type="submit">Create database-backed account</button></div>
            </div>
        </form>
        <p class="text-center mt-3 mb-0">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
