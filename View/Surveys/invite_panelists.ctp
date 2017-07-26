<div class="row-fluid padded">
	<div class="span8">
		<?php echo $this->Form->create('User'); ?>
			<div class="box">
				<div class="box-header">
					<span class="title">Invite Panelists</span>
				</div>
				<div class="box-content">
					<div class="padded separate-sections">
						<div class="form-horizontal">
							<?php
							echo $this->Form->label('Type');
							$options = array('mintvine_id' => 'Mintvine User IDs', 'anon_id' => 'OAC Anon IDs');
							echo $this->Form->input('type', array('type' => 'radio', 'options' => $options, 'legend' => FALSE, 'value' => 'mintvine_id'));
							?>
						</div>
						<div>
							<?php echo $this->Form->input('user_id', array(
								'type' => 'textarea',
								'label' => false,
							   'after' => '<small>Enter one per line.</small>'
							)); ?>
						</div>
					</div>
					<div class="form-actions">
						<?php echo $this->Form->submit('Invite Panelists', array('class' => 'btn btn-primary')); ?>
					</div>
				</div>
			</div>
		<?php echo $this->Form->end(); ?>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Description</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>
						This is a general feature, you can put<br /> 
						Mintvine User IDs, or <br />
						Anon IDs provided by OAC client, we will convert them internally to Mintvine users in this case.<br />
						The users will be invited as a separate qualification on this project.
					</p>
				</div>
			</div>
		</div>
	</div>
</div>