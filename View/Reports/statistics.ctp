<?php echo $this->Html->css('/css/mv-statistics.css'); ?>
<?php echo $this->Html->script('/js/reports.js'); 
$time_stamp = strtotime('today - 7 days');
$early_year = date('Y', $time_stamp);
$early_month = date('m', $time_stamp);
$early_day = date('d', $time_stamp);
?>
<div class="box">
	<div class="box-header">
		<span class="title">Overall Performance</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<?php echo $this->Form->create('Report'); ?>
            <div id="start_use_compare_wrapper">
                <?php echo $this->Form->input('report_date', array(
                    'div' => 'report_date input date',
                    'type' => 'date',
                    'minYear' => date('Y') - 1,
                    'maxYear' => date('Y'),
                )); ?>
                <?php echo $this->Form->input('use_compare_date', array(
                    'div' => 'use_compare_date input checkbox',
                    'type' => 'checkbox', 
                    'label' => 'Use Compare Date',
                    'checked' => false
                )); ?>
            </div>
            <div id="compare_date_wrapper">
                <?php echo $this->Form->input('compare_date', array(
                    'div' => 'compare_date input date',
                    'type' => 'date',
                    'minYear' => date('Y') - 1,
                    'maxYear' => date('Y'),
                    'selected' => array(
                        'day' => $early_day,
                        'month' => $early_month,
                        'year' => $early_year
                    )
                )); ?>
            </div>
            <div class="clearfix">
                <label>Generate Reports For:</label>
                <?php echo $this->Form->input('points2shop', array(
                    'type' => 'checkbox', 
                    'label' => 'Points2Shop',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('cint', array(
                    'type' => 'checkbox', 
                    'label' => 'Cint',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('ssi', array(
                    'type' => 'checkbox', 
                    'label' => 'SSI',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('fulcrum', array(
                    'type' => 'checkbox', 
                    'label' => 'Lucid',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('toluna', array(
                    'type' => 'checkbox', 
                    'label' => 'Toluna',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('usurv', array(
                    'type' => 'checkbox', 
                    'label' => 'USurv',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('precision', array(
                    'type' => 'checkbox', 
                    'label' => 'Precision Sample',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('socialglimpz', array(
                    'type' => 'checkbox', 
                    'label' => 'Glimpzit',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('mbd', array(
                    'type' => 'checkbox', 
                    'label' => 'MBD',
                    'checked' => true
                )); ?>
                <?php echo $this->Form->input('offers', array(
                    'type' => 'checkbox', 
                    'label' => 'Offers',
                    'checked' => false
                )); ?>
                <?php echo $this->Form->input('mintvine', array(
                    'type' => 'checkbox', 
                    'label' => 'Adhoc',
                    'checked' => false
                )); ?>
                <?php echo $this->Form->input('export', array(
                    'type' => 'checkbox', 
                    'label' => '<strong>Export data as CSV</strong>',
                )); ?>
            </div>
			<div class="clearfix">
                <label>Options:</label>
                <?php echo $this->Form->input('flush_cache_data', array(
                    'type' => 'checkbox', 
                    'label' => 'Flush Data'
                )); ?>
            </div>
		</div>
		<div class="form-actions">	
			<?php echo $this->Form->submit('See Performance', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
</div>
