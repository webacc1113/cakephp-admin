<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.action {
		width: 100px;
	}
</style>

<h3>Daily Analysis Properties</h3>

<p><?php echo $this->Html->link('Add Daily Analysis Properties', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('DailyAnalysisProperty.name', 'Name'); ?></td>
				<td class="action"></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($daily_analysis_properties as $daily_analysis_property): ?>
				<tr>
					<td>
						<?php echo $daily_analysis_property['DailyAnalysisProperty']['name']; ?>
					</td>
					<td class="nowrap action">
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $daily_analysis_property['DailyAnalysisProperty']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
						<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteDailyAnalysisProperty(' . $daily_analysis_property['DailyAnalysisProperty']['id'] . ', this)')); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>