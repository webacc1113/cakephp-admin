<p>This feature will send all parent queries to matched users.</p>
<p>Do not use this for projects that have already sent queries: this should be used only for un-launched projects.</p>
<p>If users are matched into multiple queries, they will only be notified once.</p>

<?php echo $this->Form->input('survey_id', array(
	'type' => 'hidden',
	'value' => $project['Project']['id']
)); ?>

<?php if (!empty($queries)): ?>
	
	<table class="table table-normal">
		<tr>
			<th>Query</th>
			<th>Quota</th>
			<th>Send to</th>
		</tr>
		<?php foreach ($queries as $query): ?>
			<tr>
				<td><?php echo $query['Query']['query_name']; ?></td>
				<?php if (!is_null($query['QueryStatistic']['quota'])): ?>
					<td><?php echo $query['QueryStatistic']['quota']; ?></td>
					<td><?php echo MintVine::estimate_query_send($query, $project); ?></td>
				<?php elseif (!empty($project['Project']['quota'])): ?>
					<td><?php echo $project['Project']['quota']; ?></td>
					<td><?php echo MintVine::estimate_query_send($query, $project); ?></td>
				<?php else: ?>
					<td>Unlimited</td>
					<td>All matched users</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>