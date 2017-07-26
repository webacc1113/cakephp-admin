<h3>Questions Statistics</h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Html->link('Set Core Questions', array('action' => 'core_questions'), array('class' => 'btn btn-success')); ?>
		<?php echo $this->Html->link('Export to CSV', array('action' => 'export'), array('class' => 'btn btn-success')); ?>
	</div>
	<div class="span6">
		<?php echo $this->Form->create('QuestionStatistic', array('url' => array('action' => 'index'), 'type' => 'get', 'class' => 'clearfix form-inline')); ?>
		<div class="user-groups">
			<div class="form-group">
				<?php
				echo $this->Form->input('country', array(
					'type' => 'select',
					'label' => false,
					'class' => 'select',
					'empty' => 'Filter by country',
					'options' => $countries,
					'value' => isset($country) ? $country : null,
				));
				?>
				<?php
				echo $this->Form->input('partner', array(
					'type' => 'select',
					'label' => false,
					'class' => 'select',
					'empty' => 'Filter by partner',
					'options' => $partners,
					'value' => isset($partner) ? $partner : null,
				));
				?>
				<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>#</td>
				<td>Core</td>
				<td>Settable</td>
				<td>Partner</td>
				<td>Question (Internal)</td>
				<td>Question (Display)</td>
				<td>Country</td>
				<td>Frequency</td>
			</tr>
		</thead>
		<tbody>
			<?php if (isset($this->request->params['named']['page'])): ?>
				<?php $page = $this->request->params['named']['page']; ?>
				<?php $limit = $this->Paginator->params()['limit']; ?>
				<?php $i = $limit * ($page - 1); ?>
			<?php else : ?>
				<?php $i = 0; ?>
			<?php endif; ?>
			<?php foreach ($question_statistics as $question_statistic): ?>
				<?php if (!$question_statistic['Question']['ignore'] && !$question_statistic['Question']['staging']&& !$question_statistic['Question']['deprecated']): ?>
					<?php $settable = true; ?>
					<?php $i++; ?>
				<?php else: ?>
					<?php $settable = false; ?>
				<?php endif; ?>
				<tr>
					<td><?php echo $settable ? $i: '-'; ?></td>
					<td><?php echo $question_statistic['Question']['core'] ? '<span class="label label-success">Y</span>': ''; ?></td>
					<td><?php echo $settable ? '<span class="label label-success">Y</span>': '<span class="label label-danger">N</span>'; ?></td>
					<td><?php echo $question_statistic['Question']['partner']; ?></td>
					<td><?php echo $question_statistic['Question']['question']; ?></td>
					<td><?php echo isset($question_statistic['Question']['QuestionText']['text']) ? $question_statistic['Question']['QuestionText']['text'] : '-'; ?></td>
					<td><?php echo $question_statistic['QuestionStatistic']['country']; ?></td>
					<td><?php echo $question_statistic['QuestionStatistic']['frequency']; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>