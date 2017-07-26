<div id="modal-link-instructions" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6 id="modal-tablesLabel">Link Instructions</h6>
	</div>
	<div class="modal-body">
		<p>One variable is required: <strong><code>{{ID}}</code></strong>. This will be swapped with the user identifier that is sent to the client.</p>
		<p>You can also pass <strong><code>{{PROJECT}}</code></strong> which sets the internal MV project ID</p>
		<p>You can freely define any variables in a link as well.</p>
		<p>Setting a client link as: <code>http://client-link.com/?uid={{ID}}&country={{COUNTRY}}</code>. The system will then look in the partner redirect URLs for a query parameter of "country" to be passed to the client.</p>
		<p>For MintVine, the system will automatically convert the following variables:</p>
		<ul>
			<li><code>{{USER}}</code> - user ID</li>
			<li><code>{{POSTAL}}</code> - postal code</li>
			<li><code>{{STATE}}</code> - state</li>
			<li><code>{{COUNTRY}}</code> - country</li>
			<li><code>{{BIRTHDATE}}</code> - birthdate in YYYY-MM-DD format</li>
			<li><code>{{GENDER}}</code> - gender</li>
		</ul>
	</div>
	<div class="modal-footer">
	<button class="btn btn-default" data-dismiss="modal">Close</button>
	</div>
</div>