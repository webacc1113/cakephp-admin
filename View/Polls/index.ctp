<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">
	var timer = $.timer(function() {
		$('tr[data-status="queued"]').each(function() {
			var $node = $(this);
			var id = $node.data('id');
			$.ajax({
				type: "GET",
				url: "/reports/check/" + id,
				statusCode: {
					201: function(data) {
						if (data.status == 'complete') {
							$node.data('status', 'complete');
							$('tr[data-id="'+id+'"]').data('status', 'complete');
							$('a.btn-download', $node).attr('href', data.file).show();
							$('span.btn-waiting', $node).hide();
						}
					}
				}
			});
		});
	});

	timer.set({ time : 4000, autostart : true });
</script>
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
<?php echo $this->Form->create('Poll'); ?>
<h3>Polls</h3>

<p><?php echo $this->Html->link('New Poll', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($polls)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td><?php echo $this->Paginator->sort('Poll.publish_date', 'Date'); ?></td>
				<td><?php echo $this->Paginator->sort('Poll.poll_question', 'Question'); ?></td>
				<td><?php echo $this->Paginator->sort('Poll.award', 'Award'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($polls as $poll): ?>
				<tr <?php echo (!empty($poll['Report']['id'])) ? 'data-id="'.$poll['Report']['id'].'" data-status="'.$poll['Report']['status'].'"' : '';?>>
					<td class="checkbox"><?php
						echo $this->Form->input('Poll.' . $poll['Poll']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td style="text-align:center">
						<?php echo date('m/d/y', strtotime($poll['Poll']['publish_date'])); ?>
					</td>
					<td>
						<?php echo $poll['Poll']['poll_question']; ?>
					</td>
					<td class="nowrap">
						<?php echo $poll['Poll']['award']; ?> pts
					</td>
					<td class="nowrap">
						<?php echo $this->Html->link('Results', array('action' => 'results', $poll['Poll']['id']), array('target' => '_blank', 'class' => 'btn btn-mini btn-default')); ?> 
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $poll['Poll']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
						<?php echo $this->Html->link('Report', array('controller' => 'reports', 'action' => 'poll', $poll['Poll']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
						<?php if ($poll['Report']['status'] == 'complete'): ?>
							<?php echo $this->Html->link('Download Report', array('controller' => 'reports', 'action' => 'download', $poll['Report']['id']), array('class' => 'btn btn-mini btn-default', 'target' => '_blank')); ?>
						<?php elseif ($poll['Report']['status'] == 'queued'): ?>
							<?php echo '<span class="btn-waiting">'.$this->Html->image('ajax-loader.gif').' Generating report... please wait</span>'; ?>
							<?php echo $this->Html->link('Download Report', '#', array('style' => 'display: none;', 'class' => 'btn btn-mini btn-primary btn-download', 'target' => '_blank')); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php if (!empty($polls)): ?>
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