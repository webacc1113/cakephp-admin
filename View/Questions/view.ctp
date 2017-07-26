<?php $this->Html->script('jquery.sortable.js', array('inline' => false)); ?>
<h3>Answers</h3>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Partner</td>
				<td>Question (Internal)</td>
				<td>Question (Display)</td>
				<td>Type</td>
				<td>Logic Group</td>
				<td>Behavior</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo $question['Question']['partner']; ?> #<?php echo $question['Question']['partner_question_id']; ?></td>
				<td><?php echo $question['Question']['question']; ?></td>
				<td>
					<?php 
					$question_texts = array();
					foreach ($question['QuestionText'] as $question_text) {
						$question_texts[] = $question_text['country'].': '.$question_text['text']; 
					}
					echo implode('<br/>', $question_texts); 
					?>
				</td>
				<td><?php echo $question['Question']['question_type']; ?></td>
				<td><?php echo $question['Question']['logic_group']; ?></td>
				<td><?php echo $question['Question']['behavior']; ?></td>
				</tr>
		</tbody>
	</table>
</div>

<div class="alert alert-info">Note: Each row is drag-and-drop sortable</div>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal sorted_table">
		<thead>
			<tr>
				<td>Partner</td>
				<td>Answer</td>
				<td>Code</td>
				<td></td>
			</tr>
		</thead>
		<?php foreach ($question['Answer'] as $answer): ?>
			<tr data-id="<?php echo $answer['id']; ?>">
				<td><?php echo $question['Question']['partner']; ?></td>
				<td><?php 
					$answer_texts = array();
					foreach ($answer['AnswerText'] as $answer_text) {
						$answer_texts[] = $answer_text['country'].': '.$answer_text['text']; 
					}
					echo implode('<br/>', $answer_texts); 
				?></td>
				<td><?php echo $answer['partner_answer_id']; ?></td>
				<td>

					<?php echo $this->Html->link('Skip Projects with this Answer', '#', array(
						'onclick' => 'return MintVine.SkipProjectwithAnswer('.$answer['id'].', this)',
						'class' => 'btn btn-small '.($answer['skip_project'] ? 'btn-primary': 'btn-default')
					)); ?> 
					<?php echo $this->Html->link('Ignore', '#', array(
						'onclick' => 'return MintVine.IgnoreAnswer('.$answer['id'].', this)',
						'class' => 'btn btn-small '.($answer['ignore'] ? 'btn-primary': 'btn-default')
					)); ?> 
					<?php echo $this->Html->link('Hide from PMs', '#', array(
						'onclick' => 'return MintVine.HideAnswerFromPms('.$answer['id'].', this)',
						'class' => 'btn btn-small '.($answer['hide_from_pms'] ? 'btn-primary': 'btn-default')
					)); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>

<script type="text/javascript">
	$(function () {
	  // Sortable rows
	  $('.sorted_table').sortable({
		containerSelector: 'table',
		itemPath: '> tbody',
		itemSelector: 'tr',
		placeholder: '<tr class="placeholder"/>',
		onDrop: function ($item, container, _super) {			
			var $items = $('.sorted_table').sortable("serialize").get();

			var $data = new Array();
			for (var $i = 0; $i < $items.length; $i++) {
				$data.push($items[$i].id);
			}

			$.ajax({
				type: 'POST',
				url: '/questions/ajax_order_answers/',
				data: 'order='+$data.join(','),
				statusCode: {
					201: function(data) {
					},
				}
			});		
			_super($item, container);
		}
	  });
	});
</script>