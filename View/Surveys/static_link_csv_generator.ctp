<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Static Link CSV Generator</span>
			</div>
			<div class="box-content">
				<?php echo $this->Form->create('Project'); ?>
					<div class="row-fluid">
						<div class="span12">
							<div class="padded">
								<p>You can generate a CSV containing list of hashed urls.</p>
								<?php echo $this->Form->input('url', array(
									'type' => 'text',
									'label' => 'URL Stem',
									'required' => 'required'
								)); ?>					
								<?php echo $this->Form->input('hashes', array(
									'type' => 'textarea',
									'label' => 'UID Hashes (One hash per line)',
									'required' => 'required'
								)); ?>											
							</div>
						</div>
					</div>
					<div class="form-actions">			
						<?php echo $this->Form->button(__('Generate'), array(
								'class' => 'btn btn-primary btn-sm',
								'div' => false,
								'type' => 'submit'
							)
						); ?>
					</div>
				<?php echo $this->Form->end();?>
			</div>
		</div>
	</div>

	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">How to Use This Tool</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This tool will generate a CSV file for you if all you have is a list of hashes, and a URL that will be used to generate the completed hash URLS.</p>
					<p>When inputting the URL stem, utilize <code>{{ID}}</code> as to where you want to inject the UID hash values.</p>
				</div>
			</div>
		</div>
	</div>
</div>
