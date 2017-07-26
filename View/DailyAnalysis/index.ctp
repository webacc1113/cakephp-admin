<style type="text/css">
	.table-normal tbody tr.highlight-row, .table-normal tbody tr.highlight-row td {
		background: #ee5f5b none repeat scroll 0 0;
		color: #fff;
	}
	.table-normal tbody tr.highlight-row div{
		color: #5a6573;
	}
	.result-set div.input.select, div.input.date{
		display: none;
	}
	.table-normal tbody td.highlight-column{
		background: #9DA0A3 none repeat scroll 0 0;
	}
</style>
<?php
	echo $this->Html->css('/css/jquery.tokenize');
	echo $this->Html->script('/js/jquery.tokenize'); 
?>
<script type="text/javascript">
	$(function () {
		//Loop through all Labels with class 'editable'.
		$(".editable").each(function () {
			//Reference the Label.
			var label = $(this);

			//Reference the TextBox.
			var textbox = $(this).next();
			var column = $(this).parent('td');

			//When Label is clicked, hide Label and show TextBox.
			label.click(function () {
				$(this).children('span').hide();
				$(this).children('.input.select').show();
				$(this).children('.input.date').show();
			});
			
		});
	});
	$(document).ready(function() {
		$('.properties').tokenize( {
			sortable:true,
			datas:"select",
		});	
	}); 
</script>
<h3>Daily Analysis Report</h3>

<p class="pull-right"><?php echo $this->Html->link('Manage Properties', array('controller' => 'daily_analysis_properties', 'action' => 'index'), array('class' => 'btn btn-mini btn-default')); ?></p>

<p>
	<?php echo $this->Html->link('Add Daily Analysis Report', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?>
	<?php echo $this->Html->link('Post Data to Channel', array('action' => 'post_slack'), array('class' => 'btn btn-mini btn-default')); ?>
</p>

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
		<?php echo $this->Form->create('DailyAnalysis', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Reports between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false,
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['date_from']) ? $this->data['date_from']: null
						)); ?>
						<?php echo $this->Form->input('date_to', array(
							'label' => false,
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->data['date_to']) ? $this->data['date_to']: null
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('type', array(
							'type' => 'select',
							'class' => 'uniform',
							'options' => array(
								'day-of-week' => 'Outliers Day of Week',
								'monthly' => 'Outliers Monthly',
								'topbottom' => 'Top/Bottom'
							),
							'value' => isset($this->data['type']) ? $this->data['type']: null
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

	<div class="box">
		<div class="tab-content result-set">
			<?php if (isset($this->data['type']) && $this->data['type'] == 'day-of-week'): ?>
				<div id="outliers_week">
					<?php echo $this->Form->create('DailyAnalysis', array('url' => array('controller' => 'daily_analysis','action' => 'edit'))); ?>
					<?php 
						echo $this->Form->input('type', array('type' => 'hidden', 'value' => $this->data['type'])); 
						echo $this->Form->input('date_to', array('type' => 'hidden', 'value' => $this->data['date_to'])); 
						echo $this->Form->input('date_from', array('type' => 'hidden', 'value' => $this->data['date_from'])); 
					?>
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Date</td>
									<td>Pattern</td>
									<td>Month</td>
									<td>Day of week</td>
									<td>Strict high (2 IQR)</td>
									<td>Original high (1.5 IQR)</td>
									<td>Mild high (1 IQR)</td>
									<td>Mild low (1 IQR)</td>
									<td>Original low (1.5 IQR)</td>
									<td>Strict low (2 IQR)</td>
									<td></td>
								</tr>
							</thead>
							<tbody>
								<?php if (isset($analysis_list['day-of-week']) && !empty($analysis_list['day-of-week'])): ?>
									<?php foreach ($analysis_list['day-of-week'] as $key => $list): ?>
										<?php 
											$row_highlight = '';
											if (empty($list)) {
												$row_highlight = "highlight-row";
											}
										?>
										<tr class="<?php echo $row_highlight;?>">
											<td class="editable" style="text-align:center">
												<?php echo '<span>'.date('d-m-Y', strtotime($key)).'</span>'; ?>
												<?php echo $this->Form->input("daily_analyses.day-of-week.$key.date", array(
													'label' => false,
													'default' => $key
												)); ?>
											</td>
											<td>Day of Week</td>
											<td>
												<?php echo date('M-Y', strtotime($key)); ?>
											</td>
											<td>
												<?php echo date('D', strtotime($key)); ?>
											</td>
											<td class="editable <?php echo (empty($list['strict_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['strict_high']) && !empty($list['strict_high'])) {
													echo '<span>'.implode(', ',$list['strict_high']).'</span>';
												} ?>
												<?php
													echo $this->Form->input("daily_analyses.day-of-week.$key.strict_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['strict_high'])) ? array_keys($list['strict_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['original_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['original_high']) && !empty($list['original_high'])) {
													echo '<span>'.implode(', ',$list['original_high']).'</span>';
												} ?>
												<?php
													echo $this->Form->input("daily_analyses.day-of-week.$key.original_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['original_high'])) ? array_keys($list['original_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['mild_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['mild_high']) && !empty($list['mild_high'])) {
													echo '<span>'.implode(', ',$list['mild_high']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.day-of-week.$key.mild_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['mild_high'])) ? array_keys($list['mild_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['mild_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['mild_low']) && !empty($list['mild_low'])) {
													echo '<span>'.implode(', ',$list['mild_low']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.day-of-week.$key.mild_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['mild_low'])) ? array_keys($list['mild_low']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['original_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['original_low']) && !empty($list['original_low'])) {
													echo '<span>'.implode(', ',$list['original_low']).'</span>'; 
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.day-of-week.$key.original_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['original_low'])) ? array_keys($list['original_low']) : '',
														'options' => $property_list
													));
												?>
											</td>
											<td class="editable <?php echo (empty($list['strict_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['strict_low']) && !empty($list['strict_low'])) {
													echo '<span>'.implode(', ',$list['strict_low']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.day-of-week.$key.strict_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['strict_low'])) ? array_keys($list['strict_low']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td>
												<?php 
													if (!empty($list)) {
														echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteDailyAnalysis("' . $list['common']['type'] . '", "' . $list['common']['date'] . '", this)')); 
													}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>	
							</tbody>
						</table>
						<div class="form-actions pull-right">
							<?php echo $this->Form->submit('Save Analysis Report', array('class' => 'btn btn-primary')); ?>
						</div>
						
					<?php echo $this->Form->end(null); ?>
				</div>
			<?php endif; ?>		
			<?php if (isset($this->data['type']) && $this->data['type'] == 'monthly'): ?>	
				<div id="outliers_month">
					<?php echo $this->Form->create('DailyAnalysis', array('url' => array('controller' => 'daily_analysis', 'action' => 'edit'))); ?>
						<?php 
							echo $this->Form->input('type', array('type' => 'hidden', 'value' => $this->data['type'])); 
							echo $this->Form->input('date_to', array('type' => 'hidden', 'value' => $this->data['date_to'])); 
							echo $this->Form->input('date_from', array('type' => 'hidden', 'value' => $this->data['date_from'])); 
						?>
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Date</td>
									<td>Pattern</td>
									<td>Month</td>
									<td>Day of week</td>
									<td>Strict high (2 IQR)</td>
									<td>Original high (1.5 IQR)</td>
									<td>Mild high (1 IQR)</td>
									<td>Mild low (1 IQR)</td>
									<td>Original low (1.5 IQR)</td>
									<td>Strict low (2 IQR)</td>
									<td></td>
								</tr>
							</thead>
							<tbody>
								<?php if (isset($analysis_list['monthly']) && !empty($analysis_list['monthly'])): ?>
									<?php foreach ($analysis_list['monthly'] as $key => $list): ?>
										<?php 
											$row_highlight = '';
											if (empty($list)) {
												$row_highlight = "highlight-row";
											}
										?>
										<tr class="<?php echo $row_highlight;?>">
											<td class="editable" style="text-align:center">
												<?php echo '<span>'.date('d-m-Y', strtotime($key)).'</span>'; ?>
												<?php echo $this->Form->input("daily_analyses.monthly.$key.date", array(
													'label' => false,
													'default' => $key
												)); ?>
											</td>
											<td>Month</td>
											<td>
												<?php echo date('M-Y', strtotime($key)); ?>
											</td>
											<td>
												<?php echo date('D', strtotime($key)); ?>
											</td>
											<td class="editable <?php echo (empty($list['strict_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['strict_high']) && !empty($list['strict_high'])) {
													echo '<span>'.implode(', ',$list['strict_high']).'</span>';
												} ?>
												<?php
													echo $this->Form->input("daily_analyses.monthly.$key.strict_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['strict_high'])) ? array_keys($list['strict_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['original_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['original_high']) && !empty($list['original_high'])) {
													echo '<span>'.implode(', ',$list['original_high']).'</span>';
												} ?>
												<?php
													echo $this->Form->input("daily_analyses.monthly.$key.original_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['original_high'])) ? array_keys($list['original_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['mild_high'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['mild_high']) && !empty($list['mild_high'])) {
													echo '<span>'.implode(', ',$list['mild_high']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.monthly.$key.mild_high", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['mild_high'])) ? array_keys($list['mild_high']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['mild_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['mild_low']) && !empty($list['mild_low'])) {
													echo '<span>'.implode(', ',$list['mild_low']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.monthly.$key.mild_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['mild_low'])) ? array_keys($list['mild_low']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['original_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['original_low']) && !empty($list['original_low'])) {
													echo '<span>'.implode(', ',$list['original_low']).'</span>'; 
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.monthly.$key.original_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['original_low'])) ? array_keys($list['original_low']) : '',
														'options' => $property_list
													));
												?>
											</td>
											<td class="editable <?php echo (empty($list['strict_low'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['strict_low']) && !empty($list['strict_low'])) {
													echo '<span>'.implode(', ',$list['strict_low']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.monthly.$key.strict_low", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['strict_low'])) ? array_keys($list['strict_low']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td>
												<?php 
													if (!empty($list)) {
														echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteDailyAnalysis("' . $list['common']['type'] . '", "' . $list['common']['date'] . '", this)')); 
													}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>		
							</tbody>
						</table>
						<div class="form-actions pull-right">
							<?php echo $this->Form->submit('Save Analysis Report', array('class' => 'btn btn-primary')); ?>
						</div>
					<?php echo $this->Form->end(null); ?>
				</div>
			<?php endif; ?>	
			<?php if (isset($this->data['type']) && $this->data['type'] == 'topbottom'): ?>	
				<div id="topbottom">
					<?php echo $this->Form->create('DailyAnalysis', array('url' => array('controller' => 'daily_analysis', 'action' => 'edit'))); ?>
						<?php 
							echo $this->Form->input('type', array('type' => 'hidden', 'value' => $this->data['type'])); 
							echo $this->Form->input('date_to', array('type' => 'hidden', 'value' => $this->data['date_to'])); 
							echo $this->Form->input('date_from', array('type' => 'hidden', 'value' => $this->data['date_from'])); 
						?>
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Date</td>
									<td>Pattern</td>
									<td>Month</td>
									<td>Day of week</td>
									<td>60 days Top 3</td>
									<td>60 days Top 5</td>
									<td>30 days Top 3</td>
									<td>30 days Bottom 3</td>
									<td>60 days Bottom 5</td>
									<td>60 days Bottom 3</td>
									<td></td>
								</tr>
							</thead>
							<tbody>
								<?php if (isset($analysis_list['topbottom']) && !empty($analysis_list['topbottom'])): ?>
									<?php foreach ($analysis_list['topbottom'] as $key => $list): ?>
										<?php 
											$row_highlight = '';
											if (empty($list)) {
												$row_highlight = "highlight-row";
											}
										?>
										<tr class="<?php echo $row_highlight;?>">
											<td class="editable" style="text-align:center">
												<?php echo '<span>'.date('d-m-Y', strtotime($key)).'</span>'; ?>
												<?php echo $this->Form->input("daily_analyses.topbottom.$key.date", array(
													'label' => false,
													'default' => $key
												)); ?>
											</td>
											<td>TopBottom</td>
											<td>
												<?php echo date('M-Y', strtotime($key)); ?>
											</td>
											<td>
												<?php echo date('D', strtotime($key)); ?>
											</td>
											<td  class="editable <?php echo (empty($list['60_top_3'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['60_top_3']) && !empty($list['60_top_3'])) {
													echo '<span>'.implode(', ',$list['60_top_3']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.60_top_3", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['60_top_3'])) ? array_keys($list['60_top_3']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['60_top_5'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['60_top_5']) && !empty($list['60_top_5'])) {
														echo '<span>'.implode(', ',$list['60_top_5']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.60_top_5", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['60_top_5'])) ? array_keys($list['60_top_5']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['30_top_3'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['30_top_3']) && !empty($list['30_top_3'])) {
													echo '<span>'.implode(', ',$list['30_top_3']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.30_top_3", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['30_top_3'])) ? array_keys($list['30_top_3']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['30_bottom_3'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['30_bottom_3']) && !empty($list['30_bottom_3'])) {
													echo '<span>'.implode(', ',$list['30_bottom_3']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.30_bottom_3", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['30_bottom_3'])) ? array_keys($list['30_bottom_3']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['60_bottom_5'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['60_bottom_5']) && !empty($list['60_bottom_5'])) {
													echo '<span>'.implode(', ',$list['60_bottom_5']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.60_bottom_5", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['60_bottom_5'])) ? array_keys($list['60_bottom_5']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable <?php echo (empty($list['60_bottom_3'])) ? 'highlight-column': '';?>">
												<?php if (isset($list['60_bottom_3']) && !empty($list['60_bottom_3'])) {
													echo '<span>'.implode(', ',$list['60_bottom_3']).'</span>';
												} ?>
												<?php 
													echo $this->Form->input("daily_analyses.topbottom.$key.60_bottom_3", array(
														'label' => false,
														'multiple' => true,
														'style'	=> 'display:none',
														'class' => 'properties',
														'selected' => (isset($list['60_bottom_3'])) ? array_keys($list['60_bottom_3']) : '',
														'options' => $property_list
													)); 
												?>
											</td>
											<td class="editable">
												<?php 
													if (!empty($list)) {
														echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteDailyAnalysis("' . $list['common']['type'] . '", "' . $list['common']['date'] . '", this)')); 
													}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>		
							</tbody>
						</table>
						<div class="form-actions pull-right">
							<?php echo $this->Form->submit('Save Analysis Report', array('class' => 'btn btn-primary')); ?>
						</div>
					<?php echo $this->Form->end(null); ?>
				</div>
			<?php endif; ?>	
		</div>
	</div>