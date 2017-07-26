<?php
	echo $this->Html->css('/css/jquery.tokenize');
	echo $this->Html->script('/js/jquery.tokenize'); 
?>
<script type="text/javascript">
	var daily_analysis = function(node) {
		var type = $('#DailyAnalysisType').val();
		if (type == 'outliers') {
			$('div.outliers').show();
			$('div.top-bottom').hide();
		}
		else if (type == 'topbottom') {
			$('div.outliers').hide();
			$('div.top-bottom').show();
		}
	}; 
	$(document).ready(function() {
		$('.properties').tokenize( {
			sortable:true
		});	
		daily_analysis();
		$('#DailyAnalysisType').change(function() {
			daily_analysis();
		});
	}); 
	
</script>
<?php echo $this->Form->create('DailyAnalysis', array('novalidate' => true)); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Daily Analysis</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<?php echo $this->Form->input('date', array(
						'label' => 'Date',
						'value' => date('Y-m-d')
					)); ?>
					<?php echo $this->Form->input('type', array(
						'type' => 'select', 
						'options' => array(
							'topbottom' => 'Top/Bottom',
							'outliers' => 'Outliers'
						),
					)); ?>
					<div class="outliers" style="display: none;">
						<?php echo $this->Form->input('pattern', array(
							'type' => 'select',
							'options' => array(
								'day-of-week' => 'Day of Week',
								'monthly' => 'Monthly'
							))); 
						?>
						<div class="row-fluid">
							<div class="span2"><?php 
								echo $this->Form->input('strict_high', array(
									'label' => 'Strict high (2 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('original_high', array(
									'label' => 'Original high (1.5 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('mild_high', array(
									'label' => 'Mild high (1 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('mild_low', array(
									'label' => 'Mild low (1 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('original_low', array(
									'label' => 'Original low (1.5 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('strict_low', array(
									'label' => 'Strict low (2 IQR)',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
						</div>
					</div>
					<div class="top-bottom" style="display: none;">
						<div class="row-fluid">
							<div class="span2"><?php 
								echo $this->Form->input('60_top_3', array(
									'label' => '60 days Top 3',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('60_top_5', array(
									'label' => '60 days Top 5',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('30_top_3', array(
									'label' => '30 days Top 3',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('30_bottom_3', array(
									'label' => '30 days Bottom 3',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('60_bottom_5', array(
									'label' => '60 days Bottom 5',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
							<div class="span2"><?php 
								echo $this->Form->input('60_bottom_3', array(
									'label' => '60 days Bottom 3',
									'multiple' => true,
									'class' => 'properties',
									'options' => $property_list
								)); 
							?></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Add Properties', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>