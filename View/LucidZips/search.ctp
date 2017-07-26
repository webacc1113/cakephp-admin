<h3>Lucid Zips Add</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'search'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('LucidZip', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
						<?php echo $this->Form->input('zipcode', array(
							'label' => 'Zipcode',
							'value' => isset($this->request->query['zipcode']) ? $this->request->query['zipcode']: ''
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('state_abbr', array(
							'label' => 'State Abbr',
							'value' => isset($this->request->query['state_abbr']) ? $this->request->query['state_abbr']: ''
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('city', array(
							'label' => 'City',
							'value' => isset($this->request->query['city']) ? $this->request->query['city']: ''
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Zip Code</td>
				<td>State Abbreviation</td>
				<td>DMA</td>
				<td>DMA Name</td>
				<td>MSA</td>
				<td>City</td>
				<td></td>
			</tr>
		</thead>
		<?php if (!empty($zip_codes)): ?>
			<?php foreach ($zip_codes as $zip_code) : ?>
				<tr>
					<td><?php echo $zip_code['LucidZip']['zipcode']; ?></td>
					<td><?php echo $zip_code['LucidZip']['state_abbr']; ?></td>
					<td><?php echo $zip_code['LucidZip']['dma']; ?></td>
					<td><?php echo $zip_code['LucidZip']['dma_name']; ?></td>
					<td><?php echo $zip_code['LucidZip']['msa']; ?></td>
					<td><?php echo $zip_code['LucidZip']['city']; ?></td>
					<td>
						<?php
							echo $this->Html->link(
								'Copy to Zip Code', 
								array('controller' => 'lucidZips', 'action' => 'add', $zip_code['LucidZip']['id']), 
								array('escape' => false, 'class' => 'btn btn-primary','data-target' => '#modal-copy-to-zip', 'data-toggle' => 'modal')
							);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</table>
</div>
<?php echo $this->Element('modal_copy_to_zip'); ?>