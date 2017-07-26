<h3>Groupon</h3>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'groupon'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create(null, array('type' => 'get', 'url' => array('controller' => 'offers', 'action' => 'groupon'), 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
					<?php echo $this->Form->input('user', array(
						'type' => 'text',
						'placeholder' => 'Email address or #userid',
						'value' => isset($this->data['user']) ? $this->data['user']: null
					)); ?>
					</div>
					<div class="filter">
					<?php echo $this->Form->input('order_id', array(
						'type' => 'text',
						'placeholder' => '#orderid',
						'value' => isset($this->data['order_id']) ? $this->data['order_id']: null
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

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('Ledger.order_id', 'Order ID'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.gross', 'Gross'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.commission', 'Commission'); ?></td>
				<td><?php echo $this->Paginator->sort('User.firstname', 'User'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.status', 'Order Status'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.country', 'Country'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.order', 'Transaction Date'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.locked', 'Locked'); ?></td>
				<td><?php echo $this->Paginator->sort('Ledger.transaction_id', 'Transaction ID'); ?></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($records as $record): ?>
				<tr>
					<td><?php echo $record['Ledger']['order_id'];?></td>
					<td>$<?php echo number_format(round($record['Ledger']['gross'] / 100), 2);?></td>
					<td>$<?php echo number_format(round($record['Ledger']['commission']/ 100), 2);?></td>
					<td>
					<?php if ($record['User']['id']):?>
						<?php echo $this->Element('user_dropdown', array('user' => $record['User'])); ?>
						<small><?php if ($record['User']['hellbanned']): ?>
						    <span class="text-error hellban-status">Hellbanned on <?php 
						    	echo $this->Time->format($record['User']['hellbanned_on'], Utils::dateFormatToStrftime('M d')); 
						    ?></span> 
						<?php endif; ?>
						<?php echo $record['User']['email']; ?></small>
						<?php if (!empty($record['User']['referred_by'])) : ?>
						    <br/><small class="muted">Referred by <?php echo $record['Referrer']['email']; ?> 
						    	<?php if ($record['Referrer']['hellbanned']): ?>
						    		<span class="label label-red">HELLBANNED</span>
						    	<?php endif; ?></small>
						<?php endif; ?>
						<?php if ($record['User']['hellbanned'] && !empty($record['User']['hellban_score'])): ?>
						    <br/><?php if (!empty($record['User']['hellban_score'])) : ?>
						    	<?php 
						    		echo $this->Html->link($record['User']['hellban_score'], 
						    			array('controller' => 'users', 'action' => 'quickscore', $record['User']['id']), 
						    			array(
						    				'class' => 'label label-'.($record['User']['hellban_score'] > 30 ? 'important': 'info'),
						    				'data-target' => '#modal-user-score',
						    				'data-toggle' => 'modal', 
						    			)
						    		); 
						    	?>
						    <?php endif; ?>
						    <span class="label label-inverse"><?php echo $record['User']['hellban_reason']; ?></span>
						<?php endif; ?>
						<?php if (isset($record['User']['checked']) && $record['User']['checked'] && !empty($record['User']['hellban_score'])): ?>
						    <?php if (!empty($record['User']['hellban_score'])) : ?>
						    	 <br/><?php 
						    		echo $this->Html->link($record['User']['hellban_score'],
						    			array('controller' => 'users', 'action' => 'quickscore', $record['User']['id']),
						    			array(
						    				'class' => 'label label-'.($record['User']['hellban_score'] > 30 ? 'important': 'info'),
						    				'data-target' => '#modal-user-score',
						    				'data-toggle' => 'modal', 
						    			)
						    		); 
						    	?> <div class="label label-default">Manually un-hellbanned</div>
						    <?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
					</td>
					<td><?php echo $record['Ledger']['status'];?></td>
					<td><?php echo $record['Ledger']['country'];?></td>
					<td><?php echo date('F jS, Y', strtotime($record['Ledger']['order']));?></td>
					<td><?php echo ($record['Ledger']['locked']) ? date('F jS, Y', strtotime($record['Ledger']['locked'])) : ''; ?></td>
					<td><?php echo $this->Html->link(
						$record['Ledger']['transaction_id'], 
						array(
							'controller' => 'transactions',
							'action' => 'index',
							'?' => array(
								'user' => '#'.$record['User']['id'],
								'type' => TRANSACTION_GROUPON
							)
						)
					); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php if ($records) : ?>
	<?php foreach ($records as $record): ?>
		<?php if ($record['User']['id']):?>
			<?php echo $this->Element('modal_user_hellban', array('user' => $record['User'])); ?>
			<?php echo $this->Element('modal_user_remove_hellban', array('user' => $record['User'])); ?>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?> 
<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>