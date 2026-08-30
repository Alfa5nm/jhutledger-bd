<?php
require __DIR__ . '/../../Mixed/includes/bootstrap.php';
requireRole('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $status = input('status');
    if (!$userId || !in_array($status, ['Active', 'Inactive'], true)) {
        setFlash('danger', 'Invalid user status request.');
    } elseif ($userId === (int) currentUser()['user_id']) {
        setFlash('warning', 'You cannot deactivate your own account.');
    } else {
        $statement = $pdo->prepare('UPDATE users SET user_status=? WHERE user_id=?');
        $statement->execute([$status, $userId]);
        setFlash('success', 'User status updated. No historical record was deleted.');
    }
    redirect('Shishir/admin/users.php' . (input('return_q') !== '' ? '?q=' . urlencode(input('return_q')) : ''));
}

$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT u.user_id, u.name, u.email, u.phone, u.user_status, u.created_at,
               CASE
                   WHEN s.user_id IS NOT NULL THEN 'Supplier'
                   WHEN bb.user_id IS NOT NULL THEN 'B2B Buyer'
                   WHEN bc.user_id IS NOT NULL THEN 'B2C Buyer'
                   ELSE 'Invalid'
               END AS base_role
        FROM users AS u
        LEFT JOIN supplier AS s ON s.user_id = u.user_id
        LEFT JOIN b2b_buyer AS bb ON bb.user_id = u.user_id
        LEFT JOIN b2c_buyer AS bc ON bc.user_id = u.user_id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY u.created_at DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$users = $statement->fetchAll();
$pageTitle = 'Admin user management';
require __DIR__ . '/../../Mixed/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <div class="eyebrow">Account administration</div>
            <h1>User management</h1>
            <p>Search accounts and manage access status.</p>
        </div>
    </div>
    <section class="panel mt-0">
        <form method="get" class="row g-2 mb-4">
            <div class="col-md-9">
                <label class="visually-hidden" for="q">Search users</label>
                <input
                    class="form-control"
                    id="q"
                    name="q"
                    placeholder="Search name, email, or phone"
                    value="<?=e($search)?>"
                />
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-outline-primary">Search users</button>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):?>
                    <tr>
                        <td>#<?=e($user['user_id'])?></td>
                        <td>
                            <strong> <?=e($user['name'])?> </strong>
                            <br />
                            <small class="muted"> <?=e($user['email'])?> · <?=e($user['phone'])?> </small>
                            <?php if (isAdminEmail($user['email'])):?>
                            <br />
                            <span class="badge-soft">Administrator</span>
                            <?php endif;?>
                        </td>
                        <td><?=e($user['base_role'])?></td>
                        <td>
                            <span class="<?=e(statusClass($user['user_status']))?>"> <?=e($user['user_status'])?> </span>
                        </td>
                        <td><?=e(date('d M Y', strtotime($user['created_at'])))?></td>
                        <td>
                            <?php if ((int) $user['user_id'] === (int) currentUser()['user_id']):?>
                            <span class="muted small">Current account</span>
                            <?php else:?>
                            <form method="post">
                                <?=csrfField()?>
                                <input type="hidden" name="user_id" value="<?=e($user['user_id'])?>" />
                                <input type="hidden" name="return_q" value="<?=e($search)?>" />
                                <input type="hidden" name="status" value="<?=$user['user_status'] === 'Active' ? 'Inactive' : 'Active'?>" />
                                <?php $isActive = $user['user_status'] === 'Active'; ?>
                                <button
                                    class="btn btn-sm <?=$isActive ? 'btn-outline-danger' : 'btn-outline-success'?>"
                                    type="submit"
                                >
                                    <?=$isActive ? 'Deactivate' : 'Activate'?>
                                </button>
                            </form>
                            <?php endif;?>
                        </td>
                    </tr>
                    <?php endforeach;?>
<?php if (!$users):?>
                    <tr>
                        <td colspan="6" class="text-center muted py-4">No users matched the search.</td>
                    </tr>
                    <?php endif;?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../Mixed/includes/footer.php'; ?>
