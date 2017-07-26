<?php if (isset($errors) && $errors): ?>
	<div class="alert alert-danger">
		<h5>Errors</h5>
		<?php
		foreach ($errors as $error) {
			echo $error . '<br />';
		}
		?>
	</div>
<?php endif; ?>
<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Import User Addresses (Precision)</span>
			</div>
			<div class="box-content">
				
				<?php echo $this->Form->create('UserAddress', array('type' => 'file')); ?>
				<div class="row-fluid">
					<div class="span12">
						<div class="padded">
							<?php echo $this->Form->create('UserAddress', array('type' => 'file')); ?>
							<?php
							echo $this->Form->input('file', array(
								'type' => 'file',
								'label' => 'Precision CSV data file',
							));
							?>
						</div>
					</div>
				</div>
				<div class="form-actions">
					<?php echo $this->Form->submit('Import Addresses', array('class' => 'btn btn-primary', 'div' => false)); ?>			
				</div>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div>
	</div>

</div>

