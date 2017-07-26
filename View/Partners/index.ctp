<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.id {
		width: 20px;
	}
	table tr.closed {
		color: #999;
	}
</style>

<h3>Partners</h3>

<p><?php echo $this->Html->link('New Partner', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<?php echo $this->Form->create('Partner'); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($partners)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td class="id"><?php echo $this->Paginator->sort('Partner.id', 'ID'); ?></td>
				<td><?php echo $this->Paginator->sort('Partner.partner_name', 'Partner Name'); ?></td>
				<td><?php echo $this->Paginator->sort('Partner.notes', 'Notes'); ?></td>
				<td><?php echo $this->Paginator->sort('Partner.code', 'Code'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($partners as $partner): ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('Partner.' . $partner['Partner']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td class="id">
						<?php echo $partner['Partner']['id']; ?>
					</td>
					<td>
						<?php echo $partner['Partner']['partner_name']; ?>
					</td>
					<td>
						<?php echo $partner['Partner']['notes']; ?>
					</td>
					<td>
						<?php echo $partner['Partner']['code']; ?>
					</td>
					
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $partner['Partner']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php if (!empty($partners)): ?>
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