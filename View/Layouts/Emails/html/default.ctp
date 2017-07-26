<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en">
	<head>
		<?php echo $this->Html->charset(); ?>
		<title>MintVine Email</title>
		<?php echo $this->Html->css('http://fonts.googleapis.com/css?family=Lato:300,400,700|Dosis:500,700');?>
	</head>
	<body style="background-color: #ffffff;">
	<!-- WRAPPER -->
	<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border: none; width: 600px">
		<tr>
			<td>
				<table>
					<tr>
						<td>
							<a href="<?php echo HOSTNAME_WWW; ?>">
								<?php echo $this->Html->image('email-logo-text.png', array('alt' => 'MintVine', 'style' => 'border: none; display: block;'));?>
							</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td>
				<?php echo $this->fetch('content'); ?>
			</td>
		</tr>
		<tr>
			<td>
				<?php $this->startIfEmpty('footer'); ?>
				<table align="center">
					<tr>
						<td style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #999999; font-size: 12px; font-weight: 300; text-align: center">
							You received this e-mail as a member of MintVine. Please do not reply to this email.<br />
							<a style="color: #428BCA; text-decoration: none; font-family: 'Lato', Arial, Helvetica; font-weight: 300" href="<?php echo HOSTNAME_WWW . '/page/mintvine-privacy-policy'; ?>">Privacy policy</a>|
							<a style="color: #428BCA; text-decoration: none; font-family: 'Lato', Arial, Helvetica; font-weight: 300" href="<?php echo HOSTNAME_WWW . '/page/terms-of-service'; ?>">Terms of service</a>
							<br />
							MintVine is a product of Branded Research, Inc. 343 4th Ave Ste 201, San Diego, CA 92101<br />
							<?php if (isset($unsubscribe_link)): ?>
								If you no longer wish to participate, then click here to <a style="color: #428BCA; text-decoration: none; font-family: 'Lato', Arial, Helvetica; font-weight: 300" href="<?php echo $unsubscribe_link; ?>">unsubscribe</a>.
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php $this->end(); ?>
				<?php echo $this->fetch('footer'); ?>
			</td>
		</tr>
	</table>
	<!-- END OF WRAPPER -->
	</body>
</html>
