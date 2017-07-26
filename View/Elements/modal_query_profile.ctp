<div id="modal-query-profile" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Query Profile Questions</h6>
	</div>
	<div class="modal-body">
		
		<div class="clearfix">
			<div class="span5"><?php 
				echo $this->Form->input('q', array(
					'label' => 'Find profile question by keyword',
					'id' => 'query-search'
				)); 
			?></div>
			<div class="span5"><?php 
				echo $this->Form->input('questions', array(
					'label' => 'Find by profile survey',
					'type' => 'select',
					'empty' => 'Select:',
					'options' => $questions,
					'id' => 'query-profile-dropdown'
				)); 
			?></div>
		</div>
		
		<div id="query-profile-results">
			
		</div>
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Add Query Profile Questions', array(
				'class' => 'btn btn-primary',
				'onclick' => 'return MintVine.AddQuery(this)'
			)); 
		?>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#query-profile-dropdown').change(function() {
			$('#query-search').val('');
			$.ajax({
				type: 'GET',
				url: '/profile_questions/ajax_search/?id=' + $(this).val(),
				statusCode: {
					200: function(data) {
						$('#query-profile-results').html(data.responseText);
					}
				}
			});	
		});
		$("#query-search").onDelayedKeyup({
			handler: function() {
				$('#query-profile-dropdown').val('');
				if ($.trim($(this).val()).length > 2) {					
					$.ajax({
						type: 'GET',
						url: '/profile_questions/ajax_search/?q=' + $.trim($(this).val()),
						statusCode: {
							200: function(data) {
								$('#query-profile-results').html(data.responseText);
							}
						}
					});
				}
			}
		});
	});
</script>