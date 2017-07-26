<div class="span12">
	<?php echo $this->Form->create(null); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Click to Complete Lucid</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('active', array(
					'type' => 'checkbox',
					'label' => 'Only look at active projects'
				)); ?>
				<?php echo $this->Form->input('limit', array(
					'type' => 'text',
					'label' => 'Number of projects to analyze',
					'value' => isset($this->request->data['Report']['limit']) ? $this->request->data['Report']['limit'] : '500'
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
	<?php echo $this->Form->end(); ?>

	<?php if (isset($data)) : ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Clicks to first complete</span>
		</div>
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Project #</td>
					<td>Clicks To Complete</td>
					<td>IR</td>
					<td>Completes</td>
					<td>Clicks</td>
					<td>NQ</td>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Average (Total: <?php echo count($data); ?>)</td>
					<td><?php
						echo round(array_sum($data) / count($data), 2); 
					?></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			<?php foreach ($data as $project_id => $clicks): ?>
				<tr>
					<td><?php echo $this->Html->link('#'.$project_id, array('controller' => 'surveys', 'action' => 'dashboard', $project_id)); ?></td>
					<td><?php echo number_format($clicks); ?></td>
					<td><?php 
						if (empty($statistics[$project_id]['SurveyVisitCache']['ir'])) {
							echo round($statistics[$project_id]['SurveyVisitCache']['complete'] / $statistics[$project_id]['SurveyVisitCache']['click'], 2) * 100;
						}
						else {
							echo $statistics[$project_id]['SurveyVisitCache']['ir']; 
						}
					?>%</td>
					<td><?php 
						echo $statistics[$project_id]['SurveyVisitCache']['complete']; 
					?></td>
					<td><?php 
						echo $statistics[$project_id]['SurveyVisitCache']['click']; 
					?></td>
					<td><?php 
						echo $statistics[$project_id]['SurveyVisitCache']['nq']; 
					?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<?php endif; ?>
</div>