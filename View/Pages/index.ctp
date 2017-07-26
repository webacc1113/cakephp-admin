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

<h3>Pages</h3>

<p><?php echo $this->Html->link('New Page', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<?php echo $this->Form->create('Page'); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($pages)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td class="id"><?php echo $this->Paginator->sort('Page.id', 'ID'); ?></td>
				<td><?php echo $this->Paginator->sort('Page.title', 'Title'); ?></td>
				<td><?php echo $this->Paginator->sort('Page.date_created', 'Created'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($pages as $page): ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('Page.' . $page['Page']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td class="id">
						<?php echo $page['Page']['id']; ?>
					</td>
					<td>
						<?php echo $this->Text->truncate($page['Page']['title'], 50); ?>
					</td>
					<td><?php echo $this->Time->format($page['Page']['date_created'], '%h %e', false, 'America/Los_Angeles');?></td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $page['Page']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php if (!empty($pages)): ?>
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