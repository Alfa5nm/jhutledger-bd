<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$pageTitle = 'Textile inventory, clearly connected';
require __DIR__ . '/includes/header.php';
?>
<main class="hero">
    <div class="container hero-grid">
        <section>
            <div class="eyebrow">Bangladesh textile marketplace</div>
            <h1>From surplus stock to accountable trade.</h1>
            <p>JhutLedger connects textile suppliers and buyers through one reliable inventory ledger—built on a normalized relational database.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Create an account</a>
                <a class="btn btn-outline-primary btn-lg" href="<?= e(url('login.php')) ?>">Use a demo account</a>
            </div>
        </section>
        <aside class="hero-card" aria-label="Project highlights">
            <div class="hero-stat"><strong>13 tables</strong><span>Linked with primary and foreign keys</span></div>
            <div class="hero-stat"><strong>3 roles</strong><span>Supplier, B2B buyer, B2C buyer</span></div>
            <div class="hero-stat"><strong>1 shared ledger</strong><span>Consistent stock across listing types</span></div>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

