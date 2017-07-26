<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">Hey <?php echo $user['User']['name']; ?>,</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">We've set a new password you can use to log-in to MintVine.</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">Your new password is: <strong><?php echo $password;?></strong>.</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">You can log-in at: http://<?php echo $_SERVER['HTTP_HOST'];?>/users/login?email=<?php echo $user['User']['email'];?></p>