<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">

	var timer = $.timer(function() {
		$('tr[data-status="queued"]').each(function() {
			var $node = $(this);
			var id = $node.data('id');
			$.ajax({
				type: "GET",
				url: "/source_mappings/check/" + id,
				statusCode: {
					201: function(data) {
						if (data.status == 'complete') {
							$node.data('status', 'complete');
							$('tr[data-id="'+id+'"]').data('status', 'complete');
							$('span.label', $node).removeClass('label-gray').addClass('label-green').text('Ready');
							$('a.btn-download', $node).attr('href', data.file).show();
							$('span.btn-waiting', $node).hide();
						}
					}
				}
			});
		});
	});

	timer.set({ time : 4000, autostart : true });
</script>
<div class="box">
	<div class="box-header">
		<span class="title"><?php echo $source_mapping['SourceMapping']['name']?> UTM Source Reports</span>	
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>				
				<th>Created At</th>
				<th>Date From</th>
				<th>Date To</th>
				<th>Generated By</th>
				<th></th>
			</tr>
			<?php foreach ($reports as $report) : ?>
				<tr  data-id="<?php echo $report['SourceReport']['id']; ?>" data-status="<?php echo $report['SourceReport']['status']; ?>">					
					<td><?php echo $this->Time->format($report['SourceReport']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
					<td>
						<?php echo $this->Time->format($report['SourceReport']['date_from'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
					</td>
					<td>
						<?php 
							echo $this->Time->format($report['SourceReport']['date_to'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
					</td>
					<td class="muted"><?php echo $report['Admin']['admin_user']; ?></td>
					<td><?php 
						if ($report['SourceReport']['status'] == 'complete') {
							echo $this->Html->link('Download', array('controller' => 'source_mappings', 'action' => 'download', $report['SourceReport']['id']), array('class' => 'btn btn-small btn-primary', 'title' => 'Download')); 
						}
						else {
							echo '<span class="btn-waiting">'.$this->Html->image('ajax-loader.gif').' Generating... please wait</span>';
							echo $this->Html->link('Download', '#', array('style' => 'display: none;', 'class' => 'btn btn-small btn-primary btn-download', 'target' => '_blank'));
						} ?>	
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>