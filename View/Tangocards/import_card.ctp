<div class="box">
	<div class="box-header">
		<span class="title">Import Tango cards</span>
		<span class="title pull-right"><?php echo $this->Html->link('Tango cards', array('action' => 'index'), array('class' => 'btn btn-mini btn-success')); ?> </span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<div class="row-fluid">
						<?php if ($brands): $i = 1;?>
							<?php foreach($brands as $brand): ?>
								<div class="span2">
									<div><img src="<?php echo $brand->image_url?>" class="img-responsive"></div>
									<h5><?php echo $brand->description; ?></h5>
									<p>
										SKU: <?php echo $brand->rewards[0]->sku; ?><br />
										Type: <?php echo $brand->rewards[0]->type; ?><br />
										<?php if ($brand->rewards[0]->is_variable == '1'): ?>
											<?php $val = 'Variable (' . $brand->rewards[0]->currency_code . ' ' . round($brand->rewards[0]->min_price / 100, 2) .' to '.  round($brand->rewards[0]->max_price / 100, 2). ')'; ?>
										<?php else: ?>
											<?php $val = 'Fixed Available in '; ?>
											<?php foreach($brand->rewards as $reward): ?>
												<?php $val .= $reward->currency_code .' '. round($reward->denomination / 100, 2) . ', '; ?>
											<?php endforeach; ?>
											<?php $val = rtrim($val, ', '); ?>
										<?php endif; ?>
										<?php echo $val; ?>
										<br />
										<?php $val = 'Countries: '; ?>
										<?php foreach($brand->rewards[0]->countries as $country): ?>
											<?php $val .= $country .', '; ?>
										<?php endforeach; ?>
										<?php $val = rtrim($val, ', '); ?>
										
										<?php echo $val; ?>
									</p>
									<p>
										<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal_import_card_<?php echo $i; ?>">
											Import card
										</button>
									</p>
									<?php echo $this->Element('modal_import_card', array('brand' => $brand, 'i' => $i, 'val' => $val)); ?>
								</div>
								<?php if ($i % 6 == 0): ?>
									</div><div class="row-fluid">
								<?php endif; ?>
								<?php $i++; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>