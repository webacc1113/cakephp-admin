<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Import Users (OAC)</span>
			</div>
			<div class="box-content">
				<?php echo $this->Form->create('Survey', array('type' => 'file')); ?>
				<div class="row-fluid">
					<div class="span12">
						<div class="padded">
							<?php echo $this->Form->create('Survey', array('type' => 'file')); ?>
							<?php
							echo $this->Form->input('file', array(
								'type' => 'file',
								'label' => 'OAC CSV data file',
								'after' => '<div class="alert alert-info">Note: re importing the already imported users, will update the anon_ids as per this file.</div>'
							));
							?>
						</div>
					</div>
				</div>
				<div class="form-actions">
					<?php echo $this->Form->submit('Import Users', array('class' => 'btn btn-primary', 'div' => false)); ?>			
				</div>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div>
	</div>

</div>

