<?php
require __DIR__ . '/../includes/bootstrap.php';
requireRole('supplier');
$pdo=db();$supplierId=(int)currentUser()['user_id'];$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();$action=input('action');$listingId=filter_input(INPUT_POST,'listing_id',FILTER_VALIDATE_INT)?:null;
    if($action==='archive'&&$listingId){
        $s=$pdo->prepare("UPDATE listing l JOIN textile_batch b ON b.batch_id=l.batch_id SET l.status='Inactive' WHERE l.listing_id=? AND b.supplier_id=?");
        $s->execute([$listingId,$supplierId]);setFlash($s->rowCount()?'success':'danger',$s->rowCount()?'Listing archived.':'Listing not found.');redirect('supplier/listings.php');
    }
    $batchId=(int)input('batch_id');$type=input('listing_type');$quantity=(float)input('listed_quantity');$status=input('status');
    $minimum=$type==='B2B'?(float)input('minimum_quantity'):0.0;$bulkPrice=$type==='B2B'?(float)input('bulk_unit_price'):0.0;
    $bundle=$type==='B2C'?(float)input('bundle_size'):0.0;$fixedPrice=$type==='B2C'?(float)input('fixed_unit_price'):0.0;
    if($batchId<=0||$quantity<=0)$errors[]='Select a batch and enter a positive listed quantity.';
    if(!in_array($type,['B2B','B2C'],true))$errors[]='Select a valid listing type.';
    if(!in_array($status,['Active','Inactive'],true))$errors[]='Select a valid status.';
    if($type==='B2B'&&$minimum<=0)$errors[]='Enter a positive minimum order quantity for the B2B listing.';
    if($type==='B2B'&&$minimum>$quantity)$errors[]='Minimum order quantity cannot exceed the listed quantity.';
    if($type==='B2B'&&$bulkPrice<=0)$errors[]='Enter a positive wholesale unit price.';
    if($type==='B2C'&&$bundle<=0)$errors[]='Enter a positive bundle quantity for the B2C listing.';
    if($type==='B2C'&&$bundle>$quantity)$errors[]='Bundle quantity cannot exceed the listed quantity.';
    if($type==='B2C'&&$fixedPrice<=0)$errors[]='Enter a positive retail unit price.';
    if(!$errors){
        $pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT * FROM textile_batch WHERE batch_id=? AND supplier_id=? FOR UPDATE');$s->execute([$batchId,$supplierId]);$batch=$s->fetch();
            if(!$batch)throw new RuntimeException('Batch not found.');
            if($listingId){
                $s=$pdo->prepare("SELECT l.*,IF(bl.listing_id IS NULL,'B2C','B2B') listing_type FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id LEFT JOIN b2b_listing bl ON bl.listing_id=l.listing_id WHERE l.listing_id=? AND b.supplier_id=? FOR UPDATE");$s->execute([$listingId,$supplierId]);$old=$s->fetch();
                if(!$old)throw new RuntimeException('Listing not found.');
                if((int)$old['batch_id']!==$batchId||$old['listing_type']!==$type)throw new RuntimeException('A listing cannot change its batch or sales channel after creation.');
            }
            $s=$pdo->prepare("SELECT COALESCE(SUM(listed_quantity),0) FROM listing WHERE batch_id=? AND status='Active' AND listing_id<>?");$s->execute([$batchId,$listingId?:0]);$other=(float)$s->fetchColumn();
            $remaining=max(0,(float)$batch['available_quantity']-$other);
            if($status==='Active'&&$quantity>$remaining+0.0001)throw new RuntimeException('Only '.number_format($remaining,2).' '.$batch['unit_of_measure'].' remains available for allocation in this batch.');
            if($listingId){
                $pdo->prepare('UPDATE listing SET listed_quantity=?,status=? WHERE listing_id=?')->execute([$quantity,$status,$listingId]);
                if($type==='B2B')$pdo->prepare('UPDATE b2b_listing SET minimum_quantity=?,bulk_unit_price=? WHERE listing_id=?')->execute([$minimum,$bulkPrice,$listingId]);
                else $pdo->prepare('UPDATE b2c_listing SET bundle_size=?,fixed_unit_price=? WHERE listing_id=?')->execute([$bundle,$fixedPrice,$listingId]);
                setFlash('success','Listing updated successfully.');
            }else{
                $pdo->prepare('INSERT INTO listing(batch_id,listed_quantity,status) VALUES(?,?,?)')->execute([$batchId,$quantity,$status]);$listingId=(int)$pdo->lastInsertId();
                if($type==='B2B')$pdo->prepare('INSERT INTO b2b_listing(listing_id,minimum_quantity,bulk_unit_price) VALUES(?,?,?)')->execute([$listingId,$minimum,$bulkPrice]);
                else $pdo->prepare('INSERT INTO b2c_listing(listing_id,bundle_size,fixed_unit_price) VALUES(?,?,?)')->execute([$listingId,$bundle,$fixedPrice]);
                setFlash('success','Marketplace listing created.');
            }
            $pdo->commit();redirect('supplier/listings.php');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]=$e->getMessage();}
    }
}
$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare("SELECT l.*,IF(bl.listing_id IS NULL,'B2C','B2B') listing_type,bl.minimum_quantity,bl.bulk_unit_price,bc.bundle_size,bc.fixed_unit_price FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id LEFT JOIN b2b_listing bl ON bl.listing_id=l.listing_id LEFT JOIN b2c_listing bc ON bc.listing_id=l.listing_id WHERE l.listing_id=? AND b.supplier_id=?");$s->execute([(int)$_GET['edit'],$supplierId]);$edit=$s->fetch()?:null;}
$s=$pdo->prepare("SELECT b.batch_id,b.material_type,b.color,b.available_quantity,b.unit_of_measure,COALESCE(SUM(CASE WHEN l.status='Active' THEN l.listed_quantity ELSE 0 END),0) allocated_quantity FROM textile_batch b LEFT JOIN listing l ON l.batch_id=b.batch_id WHERE b.supplier_id=? AND b.status='Active' GROUP BY b.batch_id,b.material_type,b.color,b.available_quantity,b.unit_of_measure ORDER BY b.entry_date DESC");$s->execute([$supplierId]);$batches=$s->fetchAll();
$batchFilter=filter_input(INPUT_GET,'batch_id',FILTER_VALIDATE_INT)?:null;$channelFilter=in_array(($_GET['channel']??''),['B2B','B2C'],true)?$_GET['channel']:'';
$listingSql="SELECT l.*,b.material_type,b.color,b.unit_of_measure,IF(bl.listing_id IS NULL,'B2C','B2B') listing_type,bl.minimum_quantity,bl.bulk_unit_price,bc.bundle_size,bc.fixed_unit_price FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id LEFT JOIN b2b_listing bl ON bl.listing_id=l.listing_id LEFT JOIN b2c_listing bc ON bc.listing_id=l.listing_id WHERE b.supplier_id=?";$listingParams=[$supplierId];
if($batchFilter){$listingSql.=' AND l.batch_id=?';$listingParams[]=$batchFilter;}if($channelFilter==='B2B')$listingSql.=' AND bl.listing_id IS NOT NULL';elseif($channelFilter==='B2C')$listingSql.=' AND bc.listing_id IS NOT NULL';$listingSql.=' ORDER BY l.created_at DESC';
$s=$pdo->prepare($listingSql);$s->execute($listingParams);$listings=$s->fetchAll();
$form=$edit?:['listing_id'=>'','batch_id'=>'','listing_type'=>'B2B','listed_quantity'=>'','status'=>'Active','minimum_quantity'=>'','bulk_unit_price'=>'','bundle_size'=>'','fixed_unit_price'=>''];
if(!$edit&&($_GET['prefill']??'')==='1'){
    $prefillBatch=filter_input(INPUT_GET,'batch_id',FILTER_VALIDATE_INT);$prefillType=in_array(($_GET['listing_type']??''),['B2B','B2C'],true)?$_GET['listing_type']:'';
    $owned=false;foreach($batches as $candidate){if((int)$candidate['batch_id']===$prefillBatch){$owned=true;break;}}
    if($owned&&$prefillType){
        $form['batch_id']=$prefillBatch;$form['listing_type']=$prefillType;$form['listed_quantity']=max(0,(float)($_GET['listed_quantity']??0));
        if($prefillType==='B2B'){$form['minimum_quantity']=max(0,(float)($_GET['minimum_quantity']??0));$form['bulk_unit_price']=max(0,(float)($_GET['bulk_unit_price']??0));}
        else{$form['bundle_size']=max(0,(float)($_GET['bundle_size']??0));$form['fixed_unit_price']=max(0,(float)($_GET['fixed_unit_price']??0));}
        setFlash('info','Pricing suggestion loaded. Review it before publishing; no listing has been created yet.');
    }
}
$pageTitle='Marketplace listings';require __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-head"><div><div class="eyebrow">Sales channels</div><h1>Marketplace listings</h1><p>Allocate batch stock to wholesale or retail buyers.</p></div><a class="btn btn-outline-primary" href="<?=e(url('supplier/batches.php'))?>">Manage batches</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="panel"><div class="section-heading"><div><div class="eyebrow"><?=$edit?'Existing channel':'New allocation'?></div><h2 class="h4 mb-0"><?=$edit?'Edit listing #'.e($edit['listing_id']):'Create a listing'?></h2></div><?php if($edit):?><span class="channel-badge channel-<?=e(strtolower($form['listing_type']))?>"><?=e($form['listing_type']==='B2B'?'B2B Wholesale':'B2C Retail')?></span><?php endif;?></div>
<form method="post" data-listing-form data-edit-current="<?=e($edit&&$form['status']==='Active'?$form['listed_quantity']:0)?>"><?=csrfField()?><input type="hidden" name="action" value="save"><input type="hidden" name="listing_id" value="<?=e($form['listing_id'])?>"><div class="form-grid">
<div><label class="form-label" for="batch_id">Source batch</label><select class="form-select" id="batch_id" name="batch_id" required <?=$edit?'disabled':''?>><option value="">Choose batch</option><?php foreach($batches as $batch):?><option value="<?=e($batch['batch_id'])?>" data-material="<?=e($batch['material_type'].' · '.$batch['color'])?>" data-available="<?=e($batch['available_quantity'])?>" data-allocated="<?=e($batch['allocated_quantity'])?>" data-unit="<?=e($batch['unit_of_measure'])?>" <?=(int)$form['batch_id']===(int)$batch['batch_id']?'selected':''?>>Batch #<?=e($batch['batch_id'])?> · <?=e($batch['material_type'])?> · <?=e($batch['available_quantity'])?> <?=e($batch['unit_of_measure'])?> available</option><?php endforeach;?></select><?php if($edit):?><input type="hidden" name="batch_id" value="<?=e($form['batch_id'])?>"><?php endif;?></div>
<div><label class="form-label" for="listing_type">Sales channel</label><select class="form-select" id="listing_type" name="listing_type" <?=$edit?'disabled':''?>><option value="B2B" <?=$form['listing_type']==='B2B'?'selected':''?>>B2B Wholesale</option><option value="B2C" <?=$form['listing_type']==='B2C'?'selected':''?>>B2C Retail</option></select><?php if($edit):?><input type="hidden" name="listing_type" value="<?=e($form['listing_type'])?>"><small class="form-note d-block">The source batch and sales channel are permanent. Archive and recreate the listing to change either.</small><?php endif;?></div>
<div class="full batch-allocation" data-batch-summary hidden aria-live="polite"><strong data-batch-summary-title></strong><span data-batch-summary-copy></span></div>
<div><label class="form-label" for="listed_quantity">Quantity allocated to this listing</label><div class="input-unit"><input class="form-control" id="listed_quantity" type="number" step="0.01" min="0.01" name="listed_quantity" required value="<?=e($form['listed_quantity'])?>"><span data-batch-unit>units</span></div></div><div><label class="form-label" for="listing_status">Status</label><select class="form-select" id="listing_status" name="status"><option <?=$form['status']==='Active'?'selected':''?>>Active</option><option <?=$form['status']==='Inactive'?'selected':''?>>Inactive</option></select></div>
<div class="channel-fields full" data-channel-panel="B2B" <?=$form['listing_type']==='B2B'?'':'hidden'?>>
    <div class="channel-heading"><div><span class="channel-badge channel-b2b">B2B Wholesale</span><h3 class="h5">Wholesale terms</h3></div><p>For businesses purchasing larger quantities through quotation.</p></div>
    <div class="form-grid"><div><label class="form-label" for="minimum_quantity">Minimum order quantity</label><div class="input-unit"><input class="form-control" id="minimum_quantity" type="number" step="0.01" min="0.01" name="minimum_quantity" value="<?=e($form['minimum_quantity'])?>" <?=$form['listing_type']==='B2B'?'required':'disabled'?>><span data-batch-unit>units</span></div></div><div><label class="form-label" for="bulk_unit_price">Wholesale price per unit (BDT)</label><input class="form-control" id="bulk_unit_price" type="number" step="0.01" min="0.01" name="bulk_unit_price" value="<?=e($form['bulk_unit_price'])?>" <?=$form['listing_type']==='B2B'?'required':'disabled'?>></div></div>
</div>
<div class="channel-fields full" data-channel-panel="B2C" <?=$form['listing_type']==='B2C'?'':'hidden'?>>
    <div class="channel-heading"><div><span class="channel-badge channel-b2c">B2C Retail</span><h3 class="h5">Retail terms</h3></div><p>For individuals purchasing fixed-size bundles at a listed price.</p></div>
    <div class="form-grid"><div><label class="form-label" for="bundle_size">Quantity per bundle</label><div class="input-unit"><input class="form-control" id="bundle_size" type="number" step="0.01" min="0.01" name="bundle_size" value="<?=e($form['bundle_size'])?>" <?=$form['listing_type']==='B2C'?'required':'disabled'?>><span data-batch-unit>units</span></div></div><div><label class="form-label" for="fixed_unit_price">Retail price per unit (BDT)</label><input class="form-control" id="fixed_unit_price" type="number" step="0.01" min="0.01" name="fixed_unit_price" value="<?=e($form['fixed_unit_price'])?>" <?=$form['listing_type']==='B2C'?'required':'disabled'?>></div></div>
</div>
<div class="full d-flex gap-2"><button class="btn btn-primary" type="submit" data-listing-submit><?=$edit?'Save '.e($form['listing_type']).' listing':'Publish B2B listing'?></button><?php if($edit):?><a class="btn btn-outline-secondary" href="<?=e(url('supplier/listings.php'))?>">Cancel</a><?php endif;?></div></div></form></section>
<aside class="channel-notice"><strong>One batch, separate sales channels</strong><span>A batch may be divided into separate wholesale and retail listings. Each listing ID belongs to only one buyer channel and has its own allocated quantity.</span></aside>
<section class="panel"><div class="section-heading"><div><div class="eyebrow">Channel allocations</div><h2 class="h4 mb-0">Your listings</h2></div></div><form method="get" class="filter-grid compact-listing-filters"><div><label class="form-label" for="filter_batch">Source batch</label><select class="form-select" id="filter_batch" name="batch_id"><option value="">All batches</option><?php foreach($batches as $batch):?><option value="<?=e($batch['batch_id'])?>" <?=$batchFilter===(int)$batch['batch_id']?'selected':''?>>Batch #<?=e($batch['batch_id'])?> · <?=e($batch['material_type'])?></option><?php endforeach;?></select></div><div><label class="form-label" for="filter_channel">Sales channel</label><select class="form-select" id="filter_channel" name="channel"><option value="">All channels</option><option value="B2B" <?=$channelFilter==='B2B'?'selected':''?>>B2B Wholesale</option><option value="B2C" <?=$channelFilter==='B2C'?'selected':''?>>B2C Retail</option></select></div><div class="filter-action"><button class="btn btn-primary">Apply filters</button><a href="<?=e(url('supplier/listings.php'))?>">Clear</a></div></form><div class="table-wrap"><table class="table align-middle responsive-table"><thead><tr><th>Listing / Batch</th><th>Material</th><th>Channel</th><th>Allocation</th><th>Channel terms</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($listings as $listing):?><tr><td data-label="Listing / Batch"><strong>Listing #<?=e($listing['listing_id'])?></strong><br><small class="muted">Batch #<?=e($listing['batch_id'])?></small></td><td data-label="Material"><?=e($listing['material_type'])?><br><small class="muted"><?=e($listing['color'])?></small></td><td data-label="Channel"><span class="channel-badge channel-<?=e(strtolower($listing['listing_type']))?>"><?=e($listing['listing_type']==='B2B'?'B2B Wholesale':'B2C Retail')?></span></td><td data-label="Allocation"><?=e($listing['listed_quantity'])?> <?=e($listing['unit_of_measure'])?></td><td data-label="Channel terms"><?=$listing['listing_type']==='B2B'?'Minimum '.e($listing['minimum_quantity']).' '.e($listing['unit_of_measure']).'<br><small class="muted">Wholesale '.e(money($listing['bulk_unit_price'])).' per unit</small>':'Bundle '.e($listing['bundle_size']).' '.e($listing['unit_of_measure']).'<br><small class="muted">Retail '.e(money($listing['fixed_unit_price'])).' per unit</small>'?></td><td data-label="Status"><span class="<?=e(statusClass($listing['status']))?>"><?=e($listing['status'])?></span></td><td data-label="Actions"><div class="action-row"><a class="btn btn-sm btn-outline-primary" href="?edit=<?=e($listing['listing_id'])?>">Edit</a><?php if($listing['status']==='Active'):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="archive"><input type="hidden" name="listing_id" value="<?=e($listing['listing_id'])?>"><button class="btn btn-sm btn-outline-danger">Archive</button></form><?php endif;?></div></td></tr><?php endforeach;?><?php if(!$listings):?><tr><td colspan="7" class="text-center muted py-4">No listings match these filters.</td></tr><?php endif;?></tbody></table></div></section></main>
<?php require __DIR__.'/../includes/footer.php';?>
