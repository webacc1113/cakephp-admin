<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title>MintVine
		<?php echo $title_for_layout; ?>
	</title>
	<link href="https://cdn.uservoice.com/packages/gadget.css" rel="stylesheet" type="text/css" />
</head>
<body>
	<?php echo $this->fetch('content'); ?>
	<script src="https://cdn.uservoice.com/packages/gadget.js"></script>
</body>
</html>