<h3>High Usage Questions</h3>
<span style="color: #004FCC;">Please drag and drop to order</span>
<div class="box">
	<table cellpadding="0" cellspacing="0" id="question_table" class="table table-normal">
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
			<?php foreach ($questions as $question): ?>
				<tr id="<?php echo $question['Question']['id']; ?>">
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
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script type="text/javascript">

	$(document).ready(function() {
		$('#question_table tbody').sortable({
			cursor: "auto",
			helper: function(e, ui) {
				ui.children().each(function() {
					$(this).width($(this).width);
				});
				return ui;
			},
			scroll: true,
			stop: function() {
				var sort_order = [];
				$(this).children('tr').each(function() {
					sort_order.push($(this).attr('id'));
				});
				$.ajax({
					type: 'POST',
					url: '/questions/ajax_save_high_usage_order/',
					data: {sort_order: sort_order},
					statusCode: {
						201: function (data) {

						},
					}
				});
			}
		}).disableSelection();
	});
</script>