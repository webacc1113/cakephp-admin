<h3>Codes</h3>

<p><?php echo $this->Html->link('New Code', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<?php echo $this->Form->create('Code'); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($codes)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td><?php echo $this->Paginator->sort('Code.code', 'Code'); ?></td>
				<td><?php echo $this->Paginator->sort('Code.description', 'Description'); ?></td>
				<td><?php echo $this->Paginator->sort('Code.amount', 'Amount'); ?></td>
				<td><?php echo $this->Paginator->sort('Code.quota', 'Quota'); ?></td>
				<td><?php echo $this->Paginator->sort('Code.expiration', 'Expiration'); ?></td>
				<td>Used</td>
				<td><?php echo $this->Paginator->sort('Code.active', 'Active?'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($codes as $code): ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('Code.' . $code['Code']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td>
						<?php echo $code['Code']['code'];?>
					</td>
					<td>
						<?php echo $this->Text->truncate($code['Code']['description'], 80); ?>
					</td>
					<td>
						<?php echo $code['Code']['amount']; ?> pts
					</td>
					<td>
						<?php if ($code['Code']['quota'] === null):?>
							Unlimited
						<?php else:?>
							<?php echo $code['Code']['quota']; ?>
						<?php endif;?>
					</td>
					<td>
						<?php if (!empty($code['Code']['expiration'])):?>
							<?php echo $this->Time->format($code['Code']['expiration'], Utils::dateFormatToStrftime('F jS, Y - H:i A'), false, $timezone); ?>
						<?php else:?>
							-
						<?php endif;?>
					</td>
					<td>
						<?php echo $this->Html->link(count($code['CodeRedemption']) . ' ' . __n('time', 'times', count($code['CodeRedemption'])), array(
							'controller' => 'transactions',
							'?' => array('type' => TRANSACTION_CODE, 'paid' => '', 'linked_to_id' => $code['Code']['id'])
						)); ?>
					</td>
					<td>
						<?php if ($code['Code']['active']):?>
							<?php echo $this->Html->link('Active', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.ActiveCode('.$code['Code']['id'].', this)')); ?>
						<?php else:?>
							<?php echo $this->Html->link('Inactive', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.ActiveCode('.$code['Code']['id'].', this)')); ?>
						<?php endif;?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $code['Code']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php if (!empty($codes)): ?>
			<?php echo $this->Form->submit('Delete', array(
				'name' => 'delete',
				'class' => 'btn btn-danger',
				'rel' => 'tooltip',
				'data-original-title' => 'Clicking this button will delete the selected records, This is IRREVERSIBLE.',
			));
			?>
		<?php endif; ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>