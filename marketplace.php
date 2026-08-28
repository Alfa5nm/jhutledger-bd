<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole(['b2b','b2c']);
$pdo = db();
$role = currentUser()['role'];
$type = $role === 'b2b' ? 'B2B' : 'B2C';
$search = trim((string)($_GET['q'] ?? ''));
$material = trim((string)($_GET['material'] ?? ''));
$district = trim((string)($_GET['district'] ?? ''));
$maxPrice = (string)($_GET['max_price'] ?? '');
$subtable = $type === 'B2B' ? 'b2b_listing' : 'b2c_listing';
$priceField = $type === 'B2B' ? 'x.bulk_unit_price' : 'x.fixed_unit_price';
$sql = "SELECT l.listing_id,l.batch_id,l.listed_quantity,b.material_type,b.composition,b.color,b.gsm,b.`condition`,b.available_quantity,b.unit_of_measure,b.storage_location,u.name supplier_name,u.district,
             x.*,{$priceField} unit_price
      FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id JOIN users u ON u.user_id=b.supplier_id JOIN {$subtable} x ON x.listing_id=l.listing_id
      WHERE l.status='Active' AND b.status='Active' AND l.listed_quantity>0 AND b.available_quantity>0";
$params = [];
if ($search !== '') {
    $sql .= ' AND (b.material_type LIKE ? OR b.composition LIKE ? OR b.color LIKE ? OR u.name LIKE ?)';
    $like = '%'.$search.'%';
    array_push($params, $like, $like, $like, $like);
}
if ($material !== '') {
    $sql .= ' AND b.material_type=?';
    $params[] = $material;
}
if ($district !== '') {
    $sql .= ' AND u.district=?';
    $params[] = $district;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= " AND {$priceField}<=?";
    $params[] = (float)$maxPrice;
}
$sql .= ' ORDER BY l.created_at DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$listings = $statement->fetchAll();
$materials = $pdo->query("SELECT DISTINCT material_type FROM textile_batch WHERE status='Active' ORDER BY material_type")->fetchAll(PDO::FETCH_COLUMN);
$districts = $pdo->query("SELECT DISTINCT u.district FROM users u JOIN supplier s ON s.user_id=u.user_id ORDER BY u.district")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = $type.' marketplace';
require __DIR__.'/includes/header.php';
?>
<main class="container">
<div class="page-head">
<div>
<div class="eyebrow">
<?=e($type)?> sourcing</div>
<h1>Textile marketplace</h1>
<p>Search available surplus stock from Bangladeshi suppliers.</p>
</div>
<span class="badge-soft">
<?=e(count($listings))?> results</span>
</div>
<aside class="channel-notice">
<strong>You are viewing <?=e($type === 'B2B' ? 'B2B wholesale' : 'B2C retail')?> listings</strong>
<span>Every listing belongs to one buyer channel. A supplier may allocate another quantity from the same source batch to the other channel as a separate listing.</span>
</aside>
<section class="filter-bar">
<form method="get" class="filter-grid">
<div>
<label class="form-label">Search</label>
<input class="form-control" name="q" placeholder="Material, color, supplier" value="<?=e($search)?>">
</div>
<div>
<label class="form-label">Material</label>
<select class="form-select" name="material">
<option value="">All materials</option>
<?php foreach ($materials as $item):?>
<option <?=$material === $item ? 'selected' : ''?>>
<?=e($item)?>
</option>
<?php endforeach;?>
</select>
</div>
<div>
<label class="form-label">District</label>
<select class="form-select" name="district">
<option value="">All districts</option>
<?php foreach ($districts as $item):?>
<option <?=$district === $item ? 'selected' : ''?>>
<?=e($item)?>
</option>
<?php endforeach;?>
</select>
</div>
<div>
<label class="form-label">Maximum unit price</label>
<input class="form-control" type="number" min="0" step="0.01" name="max_price" value="<?=e($maxPrice)?>">
</div>
<div class="filter-action">
<button class="btn btn-primary">Apply filters</button>
<a href="<?=e(url('marketplace.php'))?>">Clear</a>
</div>
</form>
</section>
<div class="listing-grid">
<?php foreach ($listings as $listing):$available = min((float)$listing['listed_quantity'], (float)$listing['available_quantity']);
    $image = textileImage($listing['material_type'], $listing['composition']);?>
<article class="listing-card">
<figure class="listing-image">
<img src="<?=e($image['src'])?>" alt="<?=e($image['alt'])?>" width="1200" height="800" loading="lazy" decoding="async">
<figcaption>Representative image · <?=e($image['category'])?>
</figcaption>
</figure>
<div class="listing-top">
<div>
<span class="listing-id">Listing #<?=e($listing['listing_id'])?> · Batch #<?=e($listing['batch_id'])?>
</span>
<h2>
<?=e($listing['material_type'])?>
</h2>
<p>
<?=e($listing['composition'])?> · <?=e($listing['color'])?> · <?=e($listing['gsm'])?> GSM</p>
</div>
<div class="listing-badges">
<span class="channel-badge channel-<?=e(strtolower($type))?>">
<?=e($type === 'B2B' ? 'B2B Wholesale' : 'B2C Retail')?>
</span>
<span class="fabric-chip">
<?=e($listing['condition'])?>
</span>
</div>
</div>
<dl class="listing-facts">
<div>
<dt>Available allocation</dt>
<dd>
<?=e($available)?>
<?=e($listing['unit_of_measure'])?>
</dd>
</div>
<div>
<dt>
<?=e($type === 'B2B' ? 'Wholesale price' : 'Retail price')?>
</dt>
<dd>
<?=e(money($listing['unit_price']))?> per <?=e($listing['unit_of_measure'])?>
</dd>
</div>
<div>
<dt>Supplier</dt>
<dd>
<?=e($listing['supplier_name'])?>
</dd>
</div>
<div>
<dt>Location</dt>
<dd>
<?=e($listing['district'])?>
</dd>
</div>
</dl>
<?php if ($type === 'B2B'):?>
<form method="post" action="<?=e(url('b2b/quotations.php'))?>" class="order-box">
<?=csrfField()?>
<input type="hidden" name="action" value="create">
<input type="hidden" name="listing_id" value="<?=e($listing['listing_id'])?>">
<div>
<label class="form-label">Order quantity (minimum <?=e($listing['minimum_quantity'])?>
<?=e($listing['unit_of_measure'])?>)</label>
<input class="form-control" type="number" step="0.01" min="<?=e($listing['minimum_quantity'])?>" max="<?=e($available)?>" name="requested_quantity" value="<?=e($listing['minimum_quantity'])?>" required>
</div>
<div>
<label class="form-label">Offer per <?=e($listing['unit_of_measure'])?>
</label>
<input class="form-control" type="number" step="0.01" min="0.01" name="proposed_price" value="<?=e($listing['bulk_unit_price'])?>" required>
</div>
<input type="hidden" name="expiry_date" value="<?=e(date('Y-m-d', strtotime('+7 days')))?>">
<button class="btn btn-primary full-button">Request quotation</button>
</form>
<?php else:?>
<form method="post" action="<?=e(url('b2c/orders.php'))?>" class="order-box">
<?=csrfField()?>
<input type="hidden" name="action" value="place">
<input type="hidden" name="listing_id" value="<?=e($listing['listing_id'])?>">
<div class="wide">
<label class="form-label">Order quantity (bundle of <?=e($listing['bundle_size'])?>
<?=e($listing['unit_of_measure'])?>)</label>
<input class="form-control" type="number" step="<?=e($listing['bundle_size'])?>" min="<?=e($listing['bundle_size'])?>" max="<?=e($available)?>" name="quantity" value="<?=e($listing['bundle_size'])?>" required>
</div>
<button class="btn btn-primary full-button">Place retail order</button>
</form>
<?php endif;?>
</article>
<?php endforeach;?>
<?php if (!$listings):?>
<div class="empty-state">
<h2>No matching listings</h2>
<p>Try clearing a filter or increasing the maximum price.</p>
</div>
<?php endif;?>
</div>
</main>
<?php require __DIR__.'/includes/footer.php';?>
