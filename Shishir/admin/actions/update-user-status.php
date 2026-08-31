<?php

require __DIR__ . '/../../../Mixed/includes/bootstrap.php';
requirePost();
requireRole('admin');
verifyCsrf();

// Request flow: Admin user form -> updateAccountStatus() -> users UPDATE -> user list redirect.
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$status = input('status');
try {
    if (!$userId) {
        throw new RuntimeException('Select a valid user.');
    }
    if ($userId === (int) currentUser()['user_id']) {
        throw new RuntimeException('You cannot deactivate your own account.');
    }
    updateAccountStatus(db(), $userId, $status);
    setFlash('success', 'User status updated. No historical record was deleted.');
} catch (Throwable $exception) {
    setFlash('danger', $exception->getMessage());
}
$search = input('return_q');
redirect('Shishir/admin/users.php' . ($search !== '' ? '?q=' . urlencode($search) : ''));
