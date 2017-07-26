<?php $this->Html->script('jquery.sortable.js', array('inline' => false)); ?>
<h3>Questions for <?php echo $profile['Profile']['name']; ?></h3>

<p><?php echo $this->Html->link(
	'Return to User Profiles', 
	array(
		'controller' => 'profiles', 
		'action' => 'index'
	), 
	array(
		'class' => 'btn btn-primary btn-mini'
	)
); ?> <?php echo $this->Html->link(
	'New Question', 
	'#', array(
		'class' => 'btn btn-mini btn-success',		
		'data-target' => '#modal-add-profile-question',
		'data-toggle' => 'modal', 
	)
); ?></p>
<p>Note: You can drag and drop each question to set the order - these changes will auto-save.</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal sorted_table">
		<thead>
			<tr>
				<td>Name</td>
				<td>Type</td>
				<td>Answers</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($questions as $question): ?>
				<tr data-id="<?php echo $question['ProfileQuestion']['id']; ?>">
					<td><?php echo $question['ProfileQuestion']['name']; ?></td>
					<td><?php echo $question_types[$question['ProfileQuestion']['type']]; ?></td>
					<td><?php 
						$answers = array();
						if (!empty($question['ProfileAnswer'])) {
							foreach ($question['ProfileAnswer'] as $answer) {
								$answers[] = $answer['name'];
							}
						}
						echo implode(', ', $answers); 
					?></td>
					<td class="nowrap text-right" style="width: 220px;">
						<?php echo $this->Html->link(
							'Reorder Answers',
							array('action' => 'ajax_reorder_answers', $question['ProfileQuestion']['id']), 
							array(
								'data-target' => '#modal-reorder-answers',
								'data-toggle' => 'modal', 
								'class' => 'btn btn-mini btn-warning'
							)
						); ?> 
						<?php echo $this->Html->link(
							'Edit',
							array('action' => 'ajax_edit', $question['ProfileQuestion']['id']), 
							array(
								'data-target' => '#modal-edit-question',
								'data-toggle' => 'modal', 
								'class' => 'btn btn-mini btn-warning'
							)
						); ?> 
						<?php echo $this->Html->link('Delete', '#', array(
							'class' => 'btn btn-mini btn-danger',
							'onclick' => 'MintVine.DeleteQuestion('.$question['ProfileQuestion']['id'].', this)'
						)); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->element('modal_add-profile-question'); ?>
<?php echo $this->element('modal_edit-profile-question'); ?>
<?php echo $this->element('modal_reorder-profile-answers'); ?>

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
				url: '/profile_questions/ajax_order/' + <?php echo $profile['Profile']['id']; ?>,
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