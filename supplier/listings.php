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
    $minimum=(float)input('minimum_quantity');$bulkPrice=(float)input('bulk_unit_price');$bundle=(float)input('bundle_size');$fixedPrice=(float)input('fixed_unit_price');
    if($batchId<=0||$quantity<=0)$errors[]='Select a batch and enter a positive listed quantity.';
    if(!in_array($type,['B2B','B2C'],true))$errors[]='Select a valid listing type.';
    if(!in_array($status,['Active','Inactive'],true))$errors[]='Select a valid status.';
    if($type==='B2B'&&($minimum<=0||$minimum>$quantity||$bulkPrice<0))$errors[]='B2B minimum quantity and price are invalid.';
    if($type==='B2C'&&($bundle<=0||$bundle>$quantity||$fixedPrice<0))$errors[]='B2C bundle size and price are invalid.';
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
            if($status==='Active'&&$quantity>$batch['available_quantity']-$other+0.0001)throw new RuntimeException('Listed quantity exceeds the unallocated stock in this batch.');
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
$s=$pdo->prepare("SELECT batch_id,material_type,color,available_quantity,unit_of_measure FROM textile_batch WHERE supplier_id=? AND status='Active' ORDER BY entry_date DESC");$s->execute([$supplierId]);$batches=$s->fetchAll();
$s=$pdo->prepare("SELECT l.*,b.material_type,b.color,b.unit_of_measure,IF(bl.listing_id IS NULL,'B2C','B2B') listing_type,bl.minimum_quantity,bl.bulk_unit_price,bc.bundle_size,bc.fixed_unit_price FROM listing l JOIN textile_batch b ON b.batch_id=l.batch_id LEFT JOIN b2b_listing bl ON bl.listing_id=l.listing_id LEFT JOIN b2c_listing bc ON bc.listing_id=l.listing_id WHERE b.supplier_id=? ORDER BY l.created_at DESC");$s->execute([$supplierId]);$listings=$s->fetchAll();
$form=$edit?:['listing_id'=>'','batch_id'=>'','listing_type'=>'B2B','listed_quantity'=>'','status'=>'Active','minimum_quantity'=>'','bulk_unit_price'=>'','bundle_size'=>'','fixed_unit_price'=>''];
$pageTitle='Marketplace listings';require __DIR__.'/../includes/header.php';
?>
<main class="container"><div class="page-head"><div><div class="eyebrow">Sales channels</div><h1>Marketplace listings</h1><p>Allocate batch stock to wholesale or retail buyers.</p></div><a class="btn btn-outline-primary" href="<?=e(url('supplier/batches.php'))?>">Manage batches</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="panel"><h2 class="h4"><?=$edit?'Edit listing #'.e($edit['listing_id']):'Create a listing'?></h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="save"><input type="hidden" name="listing_id" value="<?=e($form['listing_id'])?>"><div class="form-grid">
<div><label class="form-label">Batch</label><select class="form-select" name="batch_id" required><option value="">Choose batch</option><?php foreach($batches as $batch):?><option value="<?=e($batch['batch_id'])?>" <?=(int)$form['batch_id']===(int)$batch['batch_id']?'selected':''?>>#<?=e($batch['batch_id'])?> · <?=e($batch['material_type'])?> · <?=e($batch['available_quantity'])?> <?=e($batch['unit_of_measure'])?></option><?php endforeach;?></select></div>
<div><label class="form-label">Sales channel</label><select class="form-select" name="listing_type"><option <?=$form['listing_type']==='B2B'?'selected':''?>>B2B</option><option <?=$form['listing_type']==='B2C'?'selected':''?>>B2C</option></select></div>
<div><label class="form-label">Listed quantity</label><input class="form-control" type="number" step="0.01" min="0.01" name="listed_quantity" required value="<?=e($form['listed_quantity'])?>"></div><div><label class="form-label">Status</label><select class="form-select" name="status"><option <?=$form['status']==='Active'?'selected':''?>>Active</option><option <?=$form['status']==='Inactive'?'selected':''?>>Inactive</option></select></div>
<div class="channel-fields"><strong>B2B terms</strong><label class="form-label mt-2">Minimum quantity</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_quantity" value="<?=e($form['minimum_quantity'])?>"><label class="form-label mt-2">Bulk unit price (BDT)</label><input class="form-control" type="number" step="0.01" min="0" name="bulk_unit_price" value="<?=e($form['bulk_unit_price'])?>"></div>
<div class="channel-fields"><strong>B2C terms</strong><label class="form-label mt-2">Bundle size</label><input class="form-control" type="number" step="0.01" min="0" name="bundle_size" value="<?=e($form['bundle_size'])?>"><label class="form-label mt-2">Fixed unit price (BDT)</label><input class="form-control" type="number" step="0.01" min="0" name="fixed_unit_price" value="<?=e($form['fixed_unit_price'])?>"></div>
<div class="full d-flex gap-2"><button class="btn btn-primary"><?=$edit?'Save listing':'Publish listing'?></button><?php if($edit):?><a class="btn btn-outline-secondary" href="<?=e(url('supplier/listings.php'))?>">Cancel</a><?php endif;?></div></div></form></section>
<section class="panel"><h2 class="h4">Your listings</h2><div class="table-wrap"><table class="table align-middle"><thead><tr><th>Listing</th><th>Material</th><th>Channel</th><th>Quantity</th><th>Terms</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($listings as $listing):?><tr><td>#<?=e($listing['listing_id'])?></td><td><?=e($listing['material_type'])?><br><small class="muted"><?=e($listing['color'])?></small></td><td><span class="badge-soft"><?=e($listing['listing_type'])?></span></td><td><?=e($listing['listed_quantity'])?> <?=e($listing['unit_of_measure'])?></td><td><?=$listing['listing_type']==='B2B'?'Min '.e($listing['minimum_quantity']).' · '.e(money($listing['bulk_unit_price'])):e($listing['bundle_size']).' per bundle · '.e(money($listing['fixed_unit_price']))?></td><td><span class="<?=e(statusClass($listing['status']))?>"><?=e($listing['status'])?></span></td><td><div class="action-row"><a class="btn btn-sm btn-outline-primary" href="?edit=<?=e($listing['listing_id'])?>">Edit</a><?php if($listing['status']==='Active'):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="archive"><input type="hidden" name="listing_id" value="<?=e($listing['listing_id'])?>"><button class="btn btn-sm btn-outline-danger">Archive</button></form><?php endif;?></div></td></tr><?php endforeach;?><?php if(!$listings):?><tr><td colspan="7" class="text-center muted py-4">No listings yet.</td></tr><?php endif;?></tbody></table></div></section></main>
<?php require __DIR__.'/../includes/footer.php';?>
