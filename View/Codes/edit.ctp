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

<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Code</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('code', array('label' => 'Code')); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text', 'label' => 'Amount <em>(points)</em>')); ?>
					<?php echo $this->Form->input('quota', array('type' => 'text', 'label' => 'Quota <em>(Leave blank for unlimited usage)</em>'));?>
					<?php echo $this->Form->input('expiration', array('timeFormat' => '24', 'type' => 'datetime', 'minYear' => date('Y')));?>
					<?php echo $this->Form->input('description', array('rows' => '10', 'cols' => '10', 'label' => 'Code Description'));?>
					<?php echo $this->Form->input('id', array('type' => 'hidden'));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>