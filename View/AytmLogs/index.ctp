<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td style="width: 150px;">Date (GMT)</td>
				<td>Endpoint</td>
				<td>Response Code</td>
				<td>Request Data</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($aytm_logs as $aytm_log): ?>
				<tr>
					<td>
						<?php echo $this->Time->format($aytm_log['AytmLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
					</td>
					<td>
						<?php echo $aytm_log['AytmLog']['endpoint']; ?>
					</td>
					<td>
						<?php echo $aytm_log['AytmLog']['response']; ?>
					</td>
					<td>
						<?php echo $this->Html->link('View', '#', array(
							'data-target' => '#modal-aytm-log-'.$aytm_log['AytmLog']['id'], 
							'data-toggle' => 'modal'
						)); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->Element('pagination'); ?>
<?php 
	foreach ($aytm_logs as $aytm_log): 
		echo $this->Element('modal_aytm_log', array('aytm_log_id' => $aytm_log['AytmLog']['id'], 'post_raw' => $aytm_log['AytmLog']['post_raw'], 'headers_raw' => $aytm_log['AytmLog']['headers_raw'], 'get_raw' => $aytm_log['AytmLog']['get_raw'])); 
	endforeach; 
?>
<?php echo $this->Element('modal_aytm_execute'); ?>