<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.id {
		width: 20px;
	}
	table tr.closed {
		color: #999;
	}
</style>

<h3>Clients</h3>
<?php
echo $this->Form->create(null, array(
	'class' => 'clearfix form-inline',
	'type' => 'get',
	'url' => array(
		'controller' => 'clients',
		'action' => 'index',
	)
));
?>
<div class="row-fluid">
	<div class="span8">
		<p>
			<?php echo $this->Html->link('New Client', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?> 
			<?php if (isset($this->request->query['status']) && $this->request->query['status'] == 'hidden'): ?>
				<?php echo $this->Html->link('Show all clients', 
					array(
						'action' => 'index', 
						'?' => array(
							'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null
						)
					), 
					array(
						'class' => 'btn btn-mini btn-danger'
					)
				); ?>
			<?php else: ?>
				<?php echo $this->Html->link('Hidden From Reports', 
					array(
						'action' => 'index', 
						'?' => array(
							'status' => 'hidden',
							'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null
						)
					), 
					array(
						'class' => 'btn btn-mini btn-danger'
					)
				); ?>
			<?php endif; ?>
		</p>
		<p>*Once we are no longer working with a specific client, please select the "Hide From reports" button.</p>
		<p class="count">Showing <?php
			echo number_format($this->Paginator->counter(array('format' => '{:current}')));
			?> of <?php
			echo number_format($this->Paginator->counter(array('format' => '{:count}')));
			?> matches
		</p>
	</div>
	<div class="span4">
		<?php echo $this->element('user_groups'); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('Client.client_name', 'Client Name'); ?></td>
				<td>Code Names</td>
				<td>Contacts</td>
				<td>Address</td>
				<td>Autolaunch (Automated Partners Only)</td>
				<td>Quickbook ID</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($clients as $client): ?>
				<tr>
					<td><?php echo $client['Client']['client_name']; ?>
					</td>
					<td>
						<?php echo $client['Client']['code_name']; ?>
					</td>
					<td>
						<?php if (!empty($client['Client']['billing_name']) && !empty($client['Client']['billing_email'])): ?>
							<div>
								<strong>Billing Contact</strong>: <?php
									echo $this->Html->link($client['Client']['billing_name'], 'mailto:'.$client['Client']['billing_email']); 
								?>
							</div>
						<?php endif; ?>
								
						<?php if (!empty($client['Client']['project_name']) && !empty($client['Client']['project_email'])): ?>
							<div>
								<strong>Project Contact</strong>: <?php
									echo $this->Html->link($client['Client']['billing_name'], 'mailto:'.$client['Client']['billing_email']); 
								?>
							</div>
						<?php endif; ?>
					</td>
					<td>
					<?php
						// format address
						$address = array();
						if (!empty($client['Client']['address_line1'])) {
							$address[] = $client['Client']['address_line1']; 
						}
						if (!empty($client['Client']['address_line2'])) {
							$address[] = $client['Client']['address_line2']; 
						}
						$city_state = array();
						if (!empty($client['Client']['city'])) {
							$city_state[] = $client['Client']['city']; 
						}
						if (!empty($client['Client']['geo_state_id'])) {
							$city_state[] = $client['GeoState']['state_abbr']; 
						}
						$city_state = implode(', ', $city_state);
						if (!empty($client['Client']['postal_code'])) {
							$address[] = $city_state.' '.$client['Client']['postal_code'];
						}
						if (!empty($client['Client']['geo_country_id'])) {
							$address[] = $client['GeoCountry']['country']; 
						}
					?>
					<?php echo implode('<br/>', $address); ?>
					</td>
					<td>
						<?php if (!$client['Client']['do_not_autolaunch']): ?>
							<span class="label label-success">Y</span>
						<?php else: ?>
							<span class="label label-red">N</span>
						<?php endif; ?>
					</td>
					<td><?php echo !empty($client['Client']['quickbook_customer_id']) ? $client['Client']['quickbook_customer_id']: '<span class="muted">---</span>'; ?></td>
					<td class="nowrap">
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $client['Client']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
						<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteClient('.$client['Client']['id'].', this)')); ?>
						<?php 
							if ($client['Client']['hide_from_reports']) {
								echo $this->Html->link('Show In Reports', '#', array('class' => 'btn btn-mini btn-danger', 'onclick' => 'return MintVine.HideClient('.$client['Client']['id'].', this)'));
							}  
							else {
								echo $this->Html->link('Hide From Reports', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.HideClient('.$client['Client']['id'].', this)'));
							}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>