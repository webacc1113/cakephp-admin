<style type="text/css">
	#CodeExpirationMonth {
		width:100px;
	}
	#CodeExpirationDay { 
		width:60px;
	}
	#CodeExpirationYear { 
		width:80px;
	}
	#CodeExpirationHour { 
		width:60px;margin-left:20px;
	}
	#CodeExpirationMin { 
		width:60px;
	}
</style>

<?php
$currentTimeWithZeroMin = strtotime(date('Y-m-d H') . '00');
?>

<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create New Code</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('code', array(
						'label' => 'Code', 
						'after' => $this->Html->link('Autocode', '#', array(
							'class' => 'btn btn-small btn-default',
							'onclick' => 'MintVine.AutoCode($("#CodeCode"))'
						))
					)); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text', 'label' => 'Amount <em>(points)</em>')); ?>
					<?php echo $this->Form->input('quota', array('type' => 'text', 'label' => 'Quota <em>(Leave blank for unlimited usage)</em>'));?>
					<?php echo $this->Form->input('expiration', array('timeFormat' => '24', 'type' => 'datetime', 'minYear' => date('Y'), 'default' => $this->Time->convert($currentTimeWithZeroMin, 'America/Los_Angeles')));?>
					<?php echo $this->Form->input('description', array('rows' => '10', 'cols' => '10', 'label' => 'Code Description'));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>