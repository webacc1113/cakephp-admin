<h3>Prequalification Questions</h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Html->link('New Question', array('action' => 'add'), array('class' => 'btn btn-success')); ?>
		<?php echo $this->Html->link('Order High Usage Questions', array('action' => 'set_high_usage_order'), array('class' => 'btn btn-default')); ?> 
		<?php echo $this->Html->link('View Statistics', array('controller' => 'question_statistics', 'action' => 'index'), array('class' => 'btn btn-default')); ?> 
	</div>
	<div class="span6">
		<?php echo $this->Form->create('Question', array('url' => array('action' => 'index'), 'type' => 'get', 'class' => 'clearfix form-inline')); ?>
		<div class="user-groups">
			<div class="form-group">
				<?php echo $this->Form->input('partner', array(
					'type' => 'select',
					'label' => false,
					'class' => 'select',
					'empty' => 'Filter by partner',
					'options' => $partners,
					'value' => isset($partner) ? $partner : null,
				 )); ?>
				<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box"z>
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Partner</td>
				<td>Question</td>
				<td>Type</td>
				<td>Logic Group</td>
				<td>Behavior</td>
				<td></td>
				<td width="150px;"></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($questions as $question): ?>
				<tr>
					<td><?php echo $question['Question']['partner']; ?> #<?php echo $question['Question']['partner_question_id']; ?></td>
					<td>
						<?php if (!empty($question['Question']['question'])): ?>
							<?php echo $question['Question']['question']; ?>
							<?php $countries = array();
								foreach ($question['QuestionText'] as $question_text) {
									$countries[] = $question_text['country']; 
								}

								echo '['.implode(', ', $countries).']'; 
							?>
						<?php else: ?>
							<?php 
								$question_texts = array();
								foreach ($question['QuestionText'] as $question_text) {
									$question_texts[] = $question_text['country'].': '.$question_text['text']; 
								}
								echo implode('<br/>', $question_texts); 
							?>
						<?php endif; ?>
					</td>
					<td><?php echo $question['Question']['question_type']; ?></td>
					<td><?php echo $question['Question']['logic_group']; ?></td>
					<td><?php echo $question['Question']['behavior']; ?></td>
					<td style="white-space: nowrap;">
						<?php if ($question['Question']['ignore']): ?>
							<?php echo $this->Html->link('Ignore', '#', array('class' => 'btn btn-small btn-primary', 'data-ignore' => (int)$question['Question']['ignore'], 'onclick' => 'return MintVine.IgnoreQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Ignore', '#', array('class' => 'btn btn-small btn-default', 'data-ignore' => (int)$question['Question']['ignore'], 'onclick' => 'return MintVine.IgnoreQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['deprecated']): ?>
							<?php echo $this->Html->link('Deprecated', '#', array('class' => 'btn btn-small btn-primary', 'onclick' => 'return MintVine.DeprecateQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Deprecated', '#', array('class' => 'btn btn-small btn-default', 'onclick' => 'return MintVine.DeprecateQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['staging']): ?>
							<?php echo $this->Html->link('Staging', '#', array('class' => 'btn btn-small btn-primary', 'onclick' => 'return MintVine.StageQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Staging', '#', array('class' => 'btn btn-small btn-default', 'onclick' => 'return MintVine.StageQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['core']): ?>
							<?php echo $this->Html->link('Core', '#', array('class' => 'btn btn-small btn-primary', 'onclick' => 'return MintVine.CoreQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Core', '#', array('class' => 'btn btn-small btn-default', 'onclick' => 'return MintVine.CoreQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['locked']): ?>
							<?php echo $this->Html->link('Locked', '#', array('class' => 'btn btn-small btn-primary', 'onclick' => 'return MintVine.LockQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Locked', '#', array('class' => 'btn btn-small btn-default', 'onclick' => 'return MintVine.LockQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['public']): ?>
							<?php echo $this->Html->link('Public', '#;', array('class' => 'btn btn-small btn-primary')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Public', '#;', array('class' => 'btn btn-small btn-default')); ?>
						<?php endif; ?>
						<?php if ($question['Question']['high_usage']): ?>
							<?php echo $this->Html->link('Show in QEV', '#', array('class' => 'btn btn-small btn-primary', 'onclick' => 'return MintVine.SetHighUsageQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Show in QEV', '#', array('class' => 'btn btn-small btn-default', 'onclick' => 'return MintVine.SetHighUsageQuestion('. $question['Question']['id'] .', this)')); ?>
						<?php endif; ?>
					</td>
					<td style="white-space: nowrap;">
						<div class="btn-group">	
							<button class="btn btn-small btn-default dropdown-toggle" data-toggle="dropdown">Edit <span class="caret"></span></button>
							<ul class="dropdown-menu">
								<li><?php 
								echo $this->Html->link('Edit Question', array('action' => 'edit', $question['Question']['id'])); 
								?></li>
								<li><?php 
								echo $this->Html->link('Answers', array('action' => 'view', $question['Question']['id'])); 
								?></li>
								<li><?php 
								echo $this->Html->link('Edit Answers', array('action' => 'answers', $question['Question']['id'])); 
								?></li>
								<li><?php 
								echo $this->Html->link('Export to QE', array('action' => 'export_to_qe', $question['Question']['id'])); 
								?></li>
							</ul>	
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>