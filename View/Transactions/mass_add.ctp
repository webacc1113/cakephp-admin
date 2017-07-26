<div class="row-fluid padded">
	<div class="span6">
		<?php echo $this->Form->create('VirtualMassAdd'); ?>
		<div class="box">
			<div class="box-header">
				<span class="title">Mass Incentive</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<?php echo $this->Form->input('identifier_type', array(
						'type' => 'select', 
						'empty' => 'Select Identifier Type:',
						'options' => array(
							'user_id' => 'MintVine User ID',
							'partner_user_id' => 'Partner Hash',
							'hash' => 'MintVine Hash',
							'email' => 'Email'
						)
					)); ?>
					<?php echo $this->Form->input('inputs', array(
						'type' => 'textarea',
						'label' => 'User Identifiers (one per line)'
					)); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text')); ?>
					<?php echo $this->Form->input('description', array()); ?>
				</div>
				<div class="form-actions">
					<?php echo $this->Form->submit('Mass Incentive', array('class' => 'btn btn-primary')); ?>
				</div>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">User Identifiers</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>You must define the type of identifier you are using.</p>
					<dl>
						<dt>MintVine ID</dt>
						<dd>These are the internal MintVine IDs. They are simple integers. For example: "128" would be a sample MintVine ID.</dd>
						<dt>Partner Hash</dt>
						<dd>These are the hashes used by MintVine for projects. They will be long strings with dashes to break up the information. For example: <code>48144-546457-ps3dgra5-28ae08</code> is a partner hash (note the first 5 digits are the project ID, and the second set of digits are the MintVine IDs)</dd>
						<dd>Hashes</dd>
						<dd>These are hashes used by the reporting engine. They look like <code>481449e1b821dd1cb9486e003526eb298</code>. Note the first 5 digits relate to the project ID.</dd>
						<dt>Email</dt>
						<dd>Email addresses can be used in lieu of any of these identifiers</dd>
					</dl>
				</div>
			</div>
		</div>
	</div>
</div>