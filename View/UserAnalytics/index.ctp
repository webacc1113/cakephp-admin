<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Transaction', array('type' => 'get', 'class' => 'filter')); ?>
		<div class="padded separate-sections">
			<div class="row-fluid">
				<label>Find by user:</label><?php
				echo $this->Form->input('user_id', array(
					'value' => isset($this->request->query['user_id']) ? $this->request->query['user_id']: '',
					'type' => 'text',
					'label' => false,
					'required' => true
				));
				?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<?php if (isset($this->request->query['user_id'])): ?>
	<?php if ($user['User']['segment_identify']): ?>
		<div class="box">
			<div class="box-header">
				<span class="title">User's Segment Identify Cache</span>
			</div>
			<div class="box-content">
				<pre><?php echo json_encode(json_decode($user['User']['segment_identify'], true), JSON_PRETTY_PRINT); ?></pre>
			</div>
		</div>
	<?php endif; ?>
	<div class="box">
		<div class="box-header">
			<span class="title">User Analytics</span>
		</div>
		<div class="box-content">
			<?php if ($user_analytics): ?>
				<table class="table table-normal">
					<tr>
						<th width="400">Identify</th>
						<th>Body</th>
						<th>Created (GMT)</th>
						<th>Created</th>
						<th>Fired (GMT)</th>
						<th>Fired</th>
					</tr>
					<?php foreach ($user_analytics as $user_analytic): ?>
						<tr>
							<td>
							ID: <?php echo $user_analytic['UserAnalytic']['id']; ?>
							<?php if (!empty($user_analytic['UserAnalytic']['json_identify'])): ?>
								<pre><?php echo(json_encode(json_decode($user_analytic['UserAnalytic']['json_identify'], true), JSON_PRETTY_PRINT)); ?></pre>
							<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($user_analytic['UserAnalytic']['json_body'])): ?>
									<pre><?php echo(json_encode(json_decode($user_analytic['UserAnalytic']['json_body'], true), JSON_PRETTY_PRINT)); ?></pre>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $this->Time->format($user_analytic['UserAnalytic']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, 'UTC');?>
							</td>
							<td>
								<?php echo $this->Time->format($user_analytic['UserAnalytic']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
							</td>
							<td>
								<?php echo $user_analytic['UserAnalytic']['fired'] ? $this->Time->format($user_analytic['UserAnalytic']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, 'UTC') : '';?>
							</td>
							<td>
								<?php echo $user_analytic['UserAnalytic']['fired'] ? $this->Time->format($user_analytic['UserAnalytic']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone): ''; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
			<div class="form-actions">
				<?php echo $this->Element('pagination'); ?>
			</div>
		</div>
	</div>
<?php endif; ?>