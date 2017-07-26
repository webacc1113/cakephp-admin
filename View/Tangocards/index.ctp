<h3>Tango cards</h3>
<p>
	<?php echo $this->Html->link('Account', array('action' => 'account'), array('class' => 'btn btn-mini btn-success')); ?> 
	<?php echo $this->Html->link('Import card', array('action' => 'import_card'), array('class' => 'btn btn-mini btn-success')); ?> 
	<?php echo $this->Html->link('Api Orders', array('action' => 'orders'), array('class' => 'btn btn-mini btn-success')); ?> 
	<?php echo $this->Html->link('Local Orders', array('controller' => 'tangocard_orders', 'action' => 'index'), array('class' => 'btn btn-mini btn-success')); ?> 
</p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Name</td>
				<td>Transaction Name</td>
				<td>Segment Transaction Name</td>
				<td>SKU</td>
				<td>Value</td>
				<td>Conversion</td>
				<td>Min</td>
				<td>Max</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($cards as $card): ?>
				<tr>
					<td>
						<?php echo $card['Tangocard']['name']; ?>
					</td>
					<td>
						<?php echo $card['Tangocard']['transaction_name']; ?>
					</td>
					<td>
						<?php echo $card['Tangocard']['segment_transaction_name']; ?>
					</td>
					<td>
						<?php echo $card['Tangocard']['sku']; ?>
					</td>
					<td>
						<?php echo $card['Tangocard']['value']; ?>
					</td>
					<td>
						<?php echo $card['Tangocard']['conversion']; ?>
					</td>
					<td>
						<?php echo ($card['Tangocard']['min_value']) ? $card['Tangocard']['currency'] . ' ' . round($card['Tangocard']['min_value'] / 100, 2) : ''; ?>
					</td>
					<td>
						<?php echo  ($card['Tangocard']['max_value']) ? $card['Tangocard']['currency'] . ' ' . round($card['Tangocard']['max_value'] / 100, 2): ''; ?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $card['Tangocard']['id']), array('class' => 'btn btn-mini btn-primary')); ?>
						<?php echo $this->Html->link('Delete', array('action' => 'delete', $card['Tangocard']['id']), array(
								'class' => 'btn btn-mini btn-warning',
								'onclick' => 'return confirm("Are you SURE you want to delete this Tangocard? This is IRREVERSIBLE.");'
							));
						?>
					</td>
				</tr>
				<?php if(!empty($card['Children'])): ?>
					<?php foreach($card['Children'] as $child): ?>
							<tr>
								<td>
									<?php echo $child['name']; ?>
								</td>
								<td>
									<?php echo $child['transaction_name']; ?>
								</td>
								<td>
									<?php echo $child['segment_transaction_name']; ?>
								</td>
								<td>
									<?php echo $child['sku']; ?>
								</td>
								<td>
									<?php echo ($child['value']) ? $child['currency'] .' '.round($child['value'] / 100, 2) : ''; ?>
								</td>
								<td>
								</td>
								<td>
								</td>
								<td>
								</td>
								<td>
									<?php echo $this->Html->link('Edit', array('action' => 'edit', $child['id']), array('class' => 'btn btn-mini btn-primary')); ?>
									<?php echo $this->Html->link('Delete', array('action' => 'delete', $child['id']), array(
										'class' => 'btn btn-mini btn-warning',
										'onclick' => 'return confirm("Are you SURE you want to delete this Tangocard? This is IRREVERSIBLE.");'
									)); ?>
								</td>
							</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>
