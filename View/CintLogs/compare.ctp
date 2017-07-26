<?php
echo $this->Form->create(null, array(
	'class' => 'clearfix form-inline',
	'type' => 'get',
	'url' => array(
		'controller' => 'cint_logs',
		'action' => 'compare'
	)
));
?>
<div class="form-group">
	<?php echo $this->Form->input('from', array(
		'label' => false,
		'placeholder' => 'Compare Run #',
		'value' => isset($this->request->query['from']) ? $this->request->query['from']: null
		)); ?> 
		
	<?php echo $this->Form->input('to', array(
		'label' => false,
		'placeholder' => 'to Compare Run #',
		'value' => isset($this->request->query['to']) ? $this->request->query['to']: null
		)); ?>
</div>
<div class="form-group">
	<?php echo $this->Form->submit('Search', array('class' => 'btn btn-default')); ?>
</div>
			
<?php echo $this->Form->end(null); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Run #<?php echo $lower['CintLog']['run']; ?> Cint Survey ID<br/>
					<?php echo $this->Time->format($lower['CintLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
				</td>
				<td>Run <?php echo $upper['CintLog']['run']; ?> Cint Survey ID<br/>
					<?php echo $this->Time->format($upper['CintLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
				</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($total_list as $cint_survey_id): ?>
				<?php 
					if (isset($surveys[$cint_survey_id])) {
						$link = $this->Html->link('#'.$cint_survey_id, array(
							'controller' => 'surveys',
							'action' => 'dashboard',
							$surveys[$cint_survey_id]
						)); 
					}
					else {
						$link = '#'.$cint_survey_id;
					}
				?>
				<tr>
					<?php if (isset($upper_logs_keyed[$cint_survey_id])): ?>
						<td>
							<?php if (!isset($lower_logs_keyed[$cint_survey_id])) : ?>
								<strong><?php echo $link; ?></strong>
							<?php else: ?>
								<span class="muted"><?php echo $link; ?></span>
							<?php endif; ?>
						</td>
					<?php elseif (isset($lower_logs_keyed[$cint_survey_id])): ?>
						<td><span class="muted">Did not exist</span></td>
					<?php endif; ?>
				
					<?php if (isset($lower_logs_keyed[$cint_survey_id])): ?>
						<td>
							<?php if (!isset($upper_logs_keyed[$cint_survey_id])) : ?>
								<strong><?php echo $link; ?></strong>
							<?php else: ?>
								<span class="muted"><?php echo $link; ?></span>
							<?php endif; ?>
						</td>
					<?php else: ?>
						<td><span class="muted">Was removed</span></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>