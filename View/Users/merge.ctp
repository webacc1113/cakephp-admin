<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Merge Account</span>
	</div>	
	<div class="box-content">
		<div class="row-fluid">
			<div class="span3">
				<div class="padded">
					<?php echo $this->Form->input('merging_id', array(
						'label' => 'Merge from ID <small>Example: #123456</small>', 
						'type' => 'text', 
						'value' => isset($this->request->query['from']) ? '#'.$this->request->query['from']: null,
						'required' => true
					)); ?>
				</div>
			</div>
			<div class="span1">
				<div style="padding-top: 45px; text-align: center;">
					<i class="icon-arrow-right"></i> 
				</div>
			</div>
			<div class="span3">
				<div class="padded">
					<?php echo $this->Form->input('merged_id', array(
						'label' => 'Merge into ID <small>Example: #123456</small>', 
						'type' => 'text', 
						'required' => true
					)); ?>
				</div>
			</div>
		</div>	
		
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<div id="SelectPhoneNumber"></div>
				</div>
			</div>
		</div>	
		<div class="form-actions">
			<?php echo $this->Form->submit('Merge', array('class' => 'btn btn-primary', 'onclick' => 'return MintVine.CheckPhoneBeforeMerge();', 'id' => 'MergeButton')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>