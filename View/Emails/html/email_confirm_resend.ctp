<div>
	<h1 style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 700; color: #68C185;">Welcome to the MintVine community</h1>
	<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">Thanks for creating a MintVine account! Please activate your account so we can start emailing offers, surveys, and other point earning opportunities that fit your specific profile.</p>
	<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">Thank you,</p>
	<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">Team MintVine</p>
	<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">
		<?php $link = HOSTNAME_WWW.'/users/activate/' . $nonce . '/'; ?>
		<a style="
		   color: #FFFFFF; 
		   text-decoraton: none;
		   -moz-user-select: none; 
		   background-image: none; 
		   background-color: #f0554d;
		   border-color: #a5413f;
		   border-radius: 4px;
		   cursor: pointer;
		   display: inline-block;
		   font-family: 'Lato', Arial, Helvetica;
		   font-size: 16px;
		   font-weight: 400;
		   text-decoration: none;
		   line-height: 1.42857;
		   margin-bottom: 0;
		   padding: 6px 12px;
		   text-align: center;
		   vertical-align: middle;
		   white-space: nowrap;" 
		   href="<?php echo $link; ?>">Activate account</a>
	</p>
	<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 12px;">
		Or verify using this link:<br />
		<a style="color: #428BCA; text-decoration: none; font-family: 'Lato', Arial, Helvetica;" href="<?php echo $link ?>"><?php echo $link; ?></a>
	</p>
</div>