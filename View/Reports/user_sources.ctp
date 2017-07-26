<h3>Users</h3>

<style type="text/css">
	img {
		max-width: 16px;
	}
	td.gender {
		width: 34px;
		text-align: right;
	}
</style>

<script type="text/javascript">
	$(document).ready(function() {
		$('.tt').tooltip();
	});
</script>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'user_sources'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create(null, array('type' => 'get', 'class' => 'filter', 'url' => array('controller' => 'reports', 'action' => 'user_sources', 'page' => null))); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
					<?php
					echo $this->Form->input('source', array(
						'class' => 'uniform',
						'type' => 'select', 
						'empty' => 'Select Source:',
						'label' => 'Campaign',
						'options' => $source_list,
						'value' => isset($this->request->query['source']) ? $this->request->query['source']: null
					));
					?>
					</div>
				
					<div class="filter date-group">
						<label>Date between: (required)</label> 
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: date('m/d/Y', mktime(0, 0, 0, date('m'), 1, date('Y')))
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: date('m/d/Y', mktime(0, 0, 0, date('m'), date('d'), date('Y')))
						)); ?>
					</div>
					
					<div class="filter">
						<?php $timezones = unserialize(US_TIMEZONES);
						echo $this->Form->input('timezone', array(
							'class' => 'uniform',
							'type' => 'select', 
							'empty' => 'UTC',
							'label' => 'Timezone',
							'options' => $timezones,
							'value' => isset($this->request->query['timezone']) ? $this->request->query['timezone']: ''
						)); ?>
					</div>
					<div class="filter">
						<label>Options</label>
						<?php echo $this->Form->input('gender', array(
							'type' => 'checkbox',
							'label' => 'Show gender breakdown',
							'checked' => $show_gender
						)); ?>
					</div>
					<div class="filter">
						<label>Filter by Pub ID (one per line)</label>
						<?php echo $this->Form->input('pub', array(
							'type' => 'textarea',
							'label' => false,
							'style' => 'height: 36px',
							'value' => isset($this->request->query['pub']) ? $this->request->query['pub']: ''
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
				&nbsp; *Dates are in GMT
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<?php if (isset($reporting) && $reporting): ?>
<div style="padding: 10px">
<?php
echo $this->Html->link('Export', array(
    'controller' => 'reports',
    'action' => 'user_sources/1',
 	'?' => array(
		'gender' => $show_gender,
		'pub' => $show_pubs ? $this->request->query['pub'] : null,
 		'source' => isset($this->request->query['source']) ? $this->request->query['source']: null,
 		'date_from' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: date('m/d/Y', mktime(0, 0, 0, date('m')-1, 1, date('Y'))),
 		'date_to' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: date('m/d/Y', mktime(0, 0, 0, date('m'), 0, date('Y')))
 		)
 	), array('class' => 'btn btn-default btn-small')); 
?>
</div>
<?php endif;?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<?php if ($show_gender): ?>
				<tr>
					<td></td>
					<td colspan="3">Registered</td>
					<td colspan="3">Verified</td>
					<td colspan="3">Survey Start</td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td>Campaign</td>
					<td>Total</td>
					<td>Males</td>
					<td>Females</td>
					<td>Total</td>
					<td>Males</td>
					<td>Females</td>
					<td>Total</td>
					<td>Males</td>
					<td>Females</td>
					<td>Total Points</td>
					<td>Average Points</td>
					<td>Hell Banned</td>
				</tr>
			<?php else : ?>	
				<tr>
					<td>Campaign</td>
					<td>Total Registered</td>
					<td>Total Verified</td>
					<td>Total Survey Starts</td>
					<td>Total Points</td>
					<td>Average Points</td>
					<td>Hell Banned</td>
				</tr>
			<?php endif; ?>
		</thead>
		<tbody>
			<?php if (isset($reporting) && $reporting): ?>
			<?php $total_registrations = $total_activations = $total_survey_starts = $total_survey_males = $total_survey_females = $total_points = $hellbanned = $i = 0; ?>
			<?php $average_points = array(); ?>
			<?php $total_males = $total_females = $total_activated_males = $total_activated_females = 0; ?>
				<?php foreach ($reporting as $row): ?>
					<?php $i++; ?>
					<?php
						if (isset($row['publisher']) && !empty($row['publisher'])) {
							if (empty($row['total_registrations']) && empty($row['total_activations'])) {
								continue;
							}
							if ($show_pubs && !in_array($row['publisher'], $pubs)) {
								continue;
							}
						}
					?>
					<tr>
						<td>
							<?php if (isset($source_mapping)): ?>
								<?php echo $source_mapping['AcquisitionPartner']['name']; ?> 
							<?php endif; ?>
							<?php if (isset($row['publisher']) && !empty($row['publisher'])) : ?>
								<span class="muted">Pub: <?php echo $row['publisher']; ?></span>
							<?php endif; ?>
						</td>
						<td>
						<?php
						echo $this->Html->link(number_format($row['total_registrations']), array(
							'controller' => 'users',
							'action' => 'index',
						 	'?' => array(
						 		'pubid' => (isset($row['publisher'])) ? $row['publisher'] : '',
						 		'source' => $row['source'],
						 		'created_from' => $this->request->query['date_from'],
						 		'created_to' => $this->request->query['date_to']
						 		)
						 	)); 
						 ?>
						</td>
						<?php if ($show_gender): ?>
							<td><?php echo $row['males']; ?></td>
							<td><?php echo $row['females']; ?></td>
						<?php endif; ?>
						<td>
						<?php
						echo $this->Html->link(number_format($row['total_activations']), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => (isset($row['publisher'])) ? $row['publisher'] : '',
								'source' => $row['source'],
								'verified_from' => $this->request->query['date_from'],
								'verified_to' => $this->request->query['date_to'], 'active' => 1
								)
							));
						?>
						</td>
						<?php if ($show_gender): ?>
							<td><?php echo $row['activated_males']; ?></td>
							<td><?php echo $row['activated_females']; ?></td>
						<?php endif; ?>
						<td><?php 
							echo number_format($row['total_survey_starts'])
						?></td>
						<?php if ($show_gender): ?>
							<td><?php echo $row['total_survey_start_males']; ?></td>
							<td><?php echo $row['total_survey_start_females']; ?></td>
						<?php endif; ?>
						<td>
						<?php
						echo $this->Html->link((($row['total_points']) ? number_format($row['total_points']) : 0), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => (isset($row['publisher'])) ? $row['publisher'] : '',
								'source' => $row['source'],
								'created_from' => $this->request->query['date_from'],
								'created_to' => $this->request->query['date_to']
								)
							));
						?>
						</td>
						<td style="text-align: right;">
						<?php
						echo $this->Html->link((($row['average_points']) ? round($row['average_points'], 2) : 0), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => (isset($row['publisher'])) ? $row['publisher'] : '',
								'source' => $row['source'],
								'created_from' => $this->request->query['date_from'],
								'created_to' => $this->request->query['date_to']
								)
							));
						?>
						</td>
						<td>
						<?php
						echo $this->Html->link(number_format($row['hellbanned']), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => (isset($row['publisher'])) ? $row['publisher'] : '',
								'source' => $row['source'],
								'hellbanned_from' => $this->request->query['date_from'],
								'hellbanned_to' => $this->request->query['date_to'],
								'active' => 3
							)
						));
					?>
					</td>
				</tr>
				<?php if ($i == 1) : ?>
					<tr>
						<th colspan="<?php echo $show_gender ? '13': '9';?>">PUBLISHER SPECIFIC DATA</th>
					</tr>
				<?php endif; ?>
				
				<?php
				if ($i > 1) {
					$total_registrations += $row['total_registrations'];
					$total_activations += $row['total_activations'];
					$total_survey_starts += $row['total_survey_starts'];
					$average_points[] = $row['average_points'] ? round($row['average_points'], 2) : 0; 
					$total_points += $row['total_points'];
					$hellbanned += $row['hellbanned'];
					if ($show_gender) {
						$total_males += $row['males'];
						$total_females += $row['females'];
						$total_activated_males += $row['activated_males'];
						$total_activated_females += $row['activated_females'];
						$total_survey_males += $row['total_survey_start_males'];
						$total_survey_females += $row['total_survey_start_females'];
					}
				}
				?>
				<?php endforeach; ?>
				<?php if (isset($this->request->query['source']) && $this->request->query['source'] != 'all'):?>
					<tr>
						<th><?php echo $source_mapping['AcquisitionPartner']['name']; ?> (From Publishers)</th>
						<th>
						<?php
						echo $this->Html->link(number_format($total_registrations), array(
							'controller' => 'users',
							'action' => 'index',
						 	'?' => array(
						 		'pubid' => '',
						 		'source' => $this->request->query['source'],
						 		'created_from' => $this->request->query['date_from'],
						 		'created_to' => $this->request->query['date_to']
						 		)
						 	)); 
						 ?>
						</th>
						<?php if ($show_gender): ?>
							<th><?php echo $total_males; ?></th>
							<th><?php echo $total_females; ?></th>
						<?php endif; ?>
						<th>
						<?php
						echo $this->Html->link(number_format($total_activations), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => '',
								'source' => $this->request->query['source'],
								'verified_from' => $this->request->query['date_from'],
								'verified_to' => $this->request->query['date_to'], 'active' => 1
								)
							));
						?>
						</th>
						<?php if ($show_gender): ?>
							<th><?php echo $total_activated_males; ?></th>
							<th><?php echo $total_activated_females; ?></th>
						<?php endif; ?>
						<th><?php
							echo number_format($total_survey_starts);
						?></th>
						<?php if ($show_gender): ?>
							<th><?php echo $total_survey_males; ?></th>
							<th><?php echo $total_survey_females; ?></th>
						<?php endif; ?>
						<th>
						<?php
						echo $this->Html->link((($total_points) ? number_format($total_points) : 0), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => '',
								'source' => $this->request->query['source'],
								'created_from' => $this->request->query['date_from'],
								'created_to' => $this->request->query['date_to']
								)
							));
						?>
						</th>
						<th style="text-align: right;">
						<?php
						echo $this->Html->link(!empty($average_points) ? round(array_sum($average_points) / count($average_points), 2) : 0, array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => '',
								'source' => $this->request->query['source'],
								'created_from' => $this->request->query['date_from'],
								'created_to' => $this->request->query['date_to']
								)
							));
						?>
						</th>
						<th>
						<?php
						echo $this->Html->link(number_format($hellbanned), array(
							'controller' => 'users',
							'action' => 'index',
							'?' => array(
								'pubid' => '',
								'source' => $this->request->query['source'],
								'hellbanned_from' => $this->request->query['date_from'],
								'hellbanned_to' => $this->request->query['date_to'],
					//			'active' => 3
							)
						));
						?>
						</th>
					</tr>
				<?php endif;?>
			<?php endif; ?>
		</tbody>
	</table>
</div>