<script type="text/javascript">
	$(document).ready(function() {
		showRetargetType();
		$('#ProjectType').change(function() {
			showRetargetType();
		});
	});
	var showRetargetType = function() {
		if ($('#ProjectType').val() == 'targeted') {
			$('div.targeted').show();
			$('div.untargeted').hide();
		}
		else {
			$('div.targeted').hide();
			$('div.untargeted').show();
		}
	};
</script>
<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Recontact Users</span>
				<ul class="box-toolbar">
					<li>
						<?php echo $this->Html->link(__('Static Link CSV Generator'), array(
								'controller' => 'surveys',
								'action' => 'static_link_csv_generator'
							), array(
								'class' => 'btn btn-primary  btn-mini',
								'div' => false
							)
						); ?>
					</li>
				</ul>
			</div>
			<div class="box-content">
				<?php echo $this->Form->create('Project', array('type' => 'file')); ?>
				<div class="row-fluid">
					<div class="span12">
						<div class="padded">
							<p>You can import a list of users from previous projects for recontacting purposes. The hashes must match users who either disqualified (NQ) or completed the survey. No other users will be matched.</p>
							<?php echo $this->Form->input('type', array(
								'type' => 'select',
								'label' => 'Survey Type',
								'options' => array(
									'targeted' => 'Targeted links (each user has a single survey URL to access)',
									'untargeted' => 'Untargeted links (all users directed to same survey URL)'
								),
								'style' => 'width: auto',
							)); ?>
					
							<div class="targeted">
								<?php echo $this->Form->input('client', array(
									'type' => 'select',
									'options' => $clients
								)); ?>
								<?php echo $this->Form->input('file', array(
									'type' => 'file',
									'label' => 'CSV of recontact links',
								)); ?>
								<p><strong>Your CSV file must contain two columns:</strong></p>
								<ol>
									<li>First column (REQUIRED) will be client URLS with {{ID}} in the URL for the dynamic UID matching.<br/>(If this project is already set up as a recontact project, then {{ID}} is not required).</li>
									<li>Second column (REQUIRED) should be the UID hashes from the old project. They should look like this: <code>07017cdccc6b3321ce6b2f535d8ceed6b</code> (the first 5 or 6 digits should reference the original project ID)</li>
								</ol>
							</div>
							<div class="untargeted">
								<?php echo $this->Form->input('hashes', array(
									'type' => 'textarea',
									'label' => 'Hashes (One hash per line)'
								)); ?>
								<p>Note: Untargeted surveys only work for MintVine users</p>
							</div>
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

	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">How Recontacting Works</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>On this project you've created, you can import in a list of panelists from a previous project. To do so, you must upload either a <strong>targeted links CSV file</strong>, or a list of <strong>untargeted UID hashes</strong>.</p>
					<p>If you do not have either, but have a list of hashes, you can use the <?php 
						echo $this->Html->link('static link CSV generator', array('controller' => 'surveys', 'action' => 'static_link_csv_generator')); 
					?> to generate an <strong>untargeted link</strong> file.</p>
					<p>A <strong>targeted link</strong> file is used when each panelist must be redirected to an exact URL. <strong>Untargeted links</strong> should be used when you want to invite specific panelists, but you don't care what URL they access - for untargeted links you can just set the client URL as normal.</p>
					<p><span class="label label-important">IMPORTANT</span> If you are using this feature to recontact against the old UIDs (by client request), then you MUST set the "Recontact ID" in the project before you upload the links.</p>
				</div>
			</div>
		</div>
	</div>
</div>