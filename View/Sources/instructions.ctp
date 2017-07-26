<div class="box">
	<div class="box-header">
		<span class="title">Instructions for Partners</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<p>Once you have been provided your campaign URL, MintVine provides two ways of having us post back information to your systems: a server-to-server postback, or a client-side pixel.</p>
					<p><strong>Server to Server Postback</strong></p>
					<p>The server-to-server postback should be provided to MintVine as a URL. The URL itself will support special variables: {{USER_ID}} and {{GENDER}} that will postback a unique identifier and the gender of the registrant.</p>
					<p>Additional data you wish to be posted back can be appended to the original campaign URL - it will be echoed back to server-to-server postbacks.</p>
					<p>For example, assuming an initial campaign URL of <code>https://mintvine.com/landers/index/mvm/?source=yoursource</code> and a postback URL of <code>http://my-postback-domain.com/my-postback-path?id={{USER_ID}}</code>, if you want to pass in affiliate or publisher information, simply modify the campaign url to: 
						<code>http://mintvine.com/landers/index/mvm/?source=yoursource&affid=804</code>. Upon server-to-server postback completion we will post to <code>http://my-postback-domain.com/my-postback-path?id={{USER_ID}}&affid=804</code>.
						
					<p>Please provide MintVine with the key of your publisher/affiliate ID information so we can track that on our end as well - in the example above, your publisher/affiliate ID would be <code>affid</code>.</p>
					
					<p><strong>Client-Side Pixel</strong></p>
					<p>If you wish to fire Javascript or an image pixel, it may be better to utilize a client-side pixel. Client side pixels allow for any HTML to be used whenever a user has finished the registration process.</p>
					<p>In your provided HTML, you may embed {{USER_ID}} or {{GENDER}}: this will swap out those values for the live value of the registrant.</p>
					<p>To pass in additional parameters, you may embed as {{variable}} any additionally passed query parameters in the MintVine campaign URL.</p>
					<p>For example, assume an initial campaign URL of <code>https://mintvine.com/landers/index/mvm/?source=yoursource</code> and a client-side pixel value of <code><?php 
						echo htmlspecialchars('<iframe src="http://www.my-postback-pixel.com/my-postback-path?id={{USER_ID}}" scrolling="no" frameborder="0" width="1" height="1"></iframe>'); 
					?></code>.</p>
					<p>If you wanted to pass additional data to this postback pixel, modify the campaign url to: <code>https://mintvine.com/landers/index/mvm/?source=yoursource&affid=804</code> and send us your client-side pixel code of: <code><?php 
						echo htmlspecialchars('<iframe src="http://www.my-postback-pixel.com/my-postback-path?id={{USER_ID}}&affid={{affid}}" scrolling="no" frameborder="0" width="1" height="1"></iframe>'); 
					?></code>. You must explicitly define the query parameters you will be passing into the system in the client-side pixel HTML you provide us.</p>
				</div>
			</div>
		</div>
	</div>
</div>