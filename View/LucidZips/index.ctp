<h3>Lucid Zips
	<div class="pull-right">
		<?php echo $this->Html->link('Add New', array('action' => 'search'), array('class' => 'btn btn-success')); ?> 
	</div>
</h3>
<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Name</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($lucid_zips as $lucid_zip): ?>
				<tr>
					<td><?php echo $lucid_zip['LucidZip']['dma_name']; ?></td>
					<td class="nowrap text-right">
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $lucid_zip['LucidZip']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>
