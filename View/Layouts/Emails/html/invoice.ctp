<!DOCTYPE html>
<html lang="en">
	<head>
		<?php echo $this->Html->charset(); ?>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>
			BR,Inc Invoice
		</title>
		<?php echo $this->Html->css('//fonts.googleapis.com/css?family=Lato:400,700|Dosis:700');?>
	</head>
	<body>
		<div style="width: 600px; margin: 20px auto; padding: 20px; background-color: #cdcccc;">
			<div style="width: 520px; padding: 40px 40px 10px 40px; border: 1px solid #cccccc; border-radius: 4px; background-color: #FFFFFF;">
				<?php echo $this->fetch('content'); ?>
			</div>
		</div>

	</body>
</html>