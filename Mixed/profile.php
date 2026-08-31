<?php
require __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo = db();
$statement = $pdo->prepare('SELECT user_id, name, email, phone, street, city, district, postal_code, user_status, created_at FROM users WHERE user_id = ?');
$statement->execute([currentUser()['user_id']]);
$profile = $statement->fetch();
$profile = array_merge($profile, (array) ($_SESSION['profile_values'] ?? []));
unset($_SESSION['profile_values']);

$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
    <div class="page-head">
        <div>
            <div class="eyebrow">Account settings</div>
            <h1 class="h2">My profile</h1>
        </div>
        <span class="<?= e(statusClass($profile['user_status'])) ?>"> <?= e($profile['user_status']) ?> </span>
    </div>
    <div class="panel mt-0">
        <form method="post" action="<?= e(url('Mixed/actions/update-profile.php')) ?>">
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="full">
                    <label class="form-label">Email</label>
                    <input class="form-control" disabled value="<?= e($profile['email']) ?>" />
                    <div class="form-text">Email is the login identifier and cannot be changed here.</div>
                </div>
                <div>
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" required value="<?= e($profile['name']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" required value="<?= e($profile['phone']) ?>" />
                </div>
                <div class="full">
                    <label class="form-label" for="street">Street</label>
                    <input class="form-control" id="street" name="street" required value="<?= e($profile['street']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" required value="<?= e($profile['city']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="district">District</label>
                    <input class="form-control" id="district" name="district" required value="<?= e($profile['district']) ?>" />
                </div>
                <div>
                    <label class="form-label" for="postal_code">Postal code</label>
                    <input class="form-control" id="postal_code" name="postal_code" required value="<?= e($profile['postal_code']) ?>" />
                </div>
                <div class="full">
                    <button class="btn btn-primary" type="submit">Save profile changes</button>
                </div>
            </div>
        </form>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
