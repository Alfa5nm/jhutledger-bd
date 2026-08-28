<?php
require __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardPath());
}

$pageTitle = 'Bangladesh textile surplus marketplace';
require __DIR__ . '/includes/header.php';
?>
<main class="landing">
<section class="hero">
<div class="container hero-grid">
<div class="hero-copy">
<div class="eyebrow">Bangladesh's circular textile market</div>
<h1>Good fabric should not become waste.</h1>
<p>JhutLedger gives surplus textile stock a clear path from mills and suppliers to the businesses that can use it.</p>
<div class="hero-actions">
<a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Join the marketplace</a>
<a class="text-link" href="<?= e(url('login.php')) ?>">Already have an account? Log in <span aria-hidden="true">→</span>
</a>
</div>
</div>
<aside class="material-board" aria-label="Materials traded on JhutLedger">
<div class="board-head">
<span>Material board</span>
<small>Common surplus categories</small>
</div>
<div class="material-row">
<span class="fabric-swatch swatch-denim" aria-hidden="true">
</span>
<div>
<strong>Denim offcuts</strong>
<small>Woven cotton · Gazipur</small>
</div>
<span class="lot-tag">Bulk</span>
</div>
<div class="material-row">
<span class="fabric-swatch swatch-knit" aria-hidden="true">
</span>
<div>
<strong>Cotton knit</strong>
<small>Jersey remnants · Narayanganj</small>
</div>
<span class="lot-tag">Rolls</span>
</div>
<div class="material-row">
<span class="fabric-swatch swatch-jute" aria-hidden="true">
</span>
<div>
<strong>Jute blend</strong>
<small>Natural fibre · Dhaka</small>
</div>
<span class="lot-tag">Bales</span>
</div>
<div class="board-note">
<span class="board-mark">JL</span>
<p>One ledger keeps quantities consistent across wholesale and retail channels.</p>
</div>
</aside>
</div>
</section>
<section class="market-intro">
<div class="container intro-grid">
<div>
<div class="eyebrow">Made for the local trade</div>
<h2>One market, three ways to participate.</h2>
</div>
<p>Suppliers record available batches. Wholesale buyers follow larger lots and quotations. Retail buyers find quantities suited to smaller orders.</p>
</div>
<div class="container role-grid">
<article class="role-item">
<span>01</span>
<h3>Supply</h3>
<p>Keep batch quantities, listings, and incoming interest in one place.</p>
<strong>For mills & suppliers</strong>
</article>
<article class="role-item">
<span>02</span>
<h3>Source wholesale</h3>
<p>Review larger lots and keep quotation activity tied to each listing.</p>
<strong>For B2B buyers</strong>
</article>
<article class="role-item">
<span>03</span>
<h3>Buy smaller lots</h3>
<p>Follow retail-ready material and order activity without losing stock context.</p>
<strong>For B2C buyers</strong>
</article>
</div>
</section>
<section class="ledger-callout">
<div class="container ledger-grid">
<div>
<div class="eyebrow">Traceable by design</div>
<h2>From leftover material to accountable trade.</h2>
</div>
<div>
<p>Every account has a clear role. Every stock movement belongs to a batch. Every order stays connected to its history.</p>
<a class="text-link light" href="<?= e(url('register.php')) ?>">Create your JhutLedger account <span aria-hidden="true">→</span>
</a>
</div>
</div>
</section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
