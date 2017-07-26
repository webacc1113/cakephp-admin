<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title>MintVine
		<?php echo $title_for_layout; ?>
	</title>
	<?php
		echo $this->Html->css('/assets/stylesheets/application');
		echo $this->Html->css('/css/styles');
		
		echo $this->Html->script('/js/jquery-1.10.2.min'); 
		echo $this->Html->script('/js/chart.min'); 
		echo $this->Html->meta('icon');
		
		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
	?>
</head>
<body>
	<div class="container-fluid">
		<div class="row-fluid padded">
			<div class="box">
				<div class="box-header">
					<span class="title">Poll Results</span>
				</div>
				<div class="box-content">
					<div class="padded">	
						<?php echo $this->Session->flash(); ?>
						<?php echo $this->fetch('content'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
