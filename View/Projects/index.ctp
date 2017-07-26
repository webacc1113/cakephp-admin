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
	table thead tr td {
		white-space: nowrap;
	}
</style>

<?php echo $this->Html->script('/js/table-fixed-header'); ?>
<script type="text/javascript">
	$(document).ready(function() {
		$('body').tooltip({
			selector: '[data-toggle=tooltip]'
		});
		$('.table-fixed-header').fixedHeader();
		$('#search_clients').autocomplete({
			source: 'projects/ajax_search_clients/',
			autoFocus: true,
			select: function(event, ui) {
				var client_id = ui.item.id;
				var html_str = '<input type="hidden" name="client_id" value="' + client_id + '" id="AdminClientId">';
				$('#client_search_area form').append(html_str);
			}
		});
	});
</script>

<div class="pull-right">
	<?php 
	if (!isset($group)) {
		$group = $mintvine_group;
	}
	?>
	<?php echo $this->Html->link('Create Project', array('controller' => 'surveys', 'action' => 'add', $group['Group']['id']), array('class' => 'btn btn-success')); ?> 
	<?php echo $this->Html->link('Run Feasibility Query', array('controller' => 'queries', 'action' => 'add'), array('class' => 'btn btn-default')); ?> 
	<?php echo $this->Html->link('Run Feasibility Query (v2)', array('controller' => 'qualifications', 'action' => 'query', 'us'), array('class' => 'btn btn-default')); ?> 
	
	<?php if ($status_filter == PROJECT_STATUS_INVOICED) : ?>
		<?php echo $this->Html->link('Invoices', array('controller' => 'invoices', 'action' => 'index'), array('class' => 'btn btn-primary')); ?>
	<?php endif; ?>
</div>

<div id="projects">
	<div class="row-fluid">
		<div class="span4">
			<?php
			echo $this->Form->create(null, array(
				'class' => 'clearfix form-inline',
				'type' => 'get',
				'url' => array(
					'controller' => 'projects',
					'action' => 'index',
				),
			));
			?>
			<div class="form-group">
				<?php
				echo $this->Form->input('q', array(
					'label' => false,
					'placeholder' => 'Project # or name',
					'value' => isset($this->request->query['q']) ? $this->request->query['q'] : null
				));
				?> 

				<?php if (isset($this->request->query['q'])): ?>
					<div class="input"><?php 
						echo $this->Html->link('Search Globally', array(
							'controller' => 'projects',
							'action' => 'index',
							'?' => array(
								'q' => $this->request->query['q'],
								'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null,
								'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
								'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null,
								'global' => 'true'
							)
						), array(
							'class' => 'btn-small btn btn-success'
						)); 
					?></div>
				<?php endif; ?>
			</div>
			<div class="form-group">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-default btn-small')); ?> 
			</div>
			
			<?php if (isset($this->request->query['group_id']) && !empty($this->request->query['group_id'])): ?>
			<?php echo $this->Form->input('group_id', array(
				'type' => 'hidden',
				'value' => $this->request->query['group_id']
			)); ?>
			<?php endif; ?>
			<?php if (isset($this->request->query['admin_id']) && !empty($this->request->query['admin_id'])): ?>
				<?php echo $this->Form->input('admin_id', array(
					'type' => 'hidden',
					'value' => $this->request->query['admin_id']
				)); ?>
			<?php endif; ?>
			<?php if (isset($this->request->query['client_id']) && !empty($this->request->query['client_id'])): ?>
				<?php echo $this->Form->input('client_id', array(
					'type' => 'hidden',
					'value' => $this->request->query['client_id']
				)); ?>
			<?php endif; ?>
			<?php echo $this->Form->end(null); ?>
		</div>
		<div class="span3" id="client_search_area" style="margin-left: 0;">
			<div class="form-group">
				<?php
					if (isset($client)) {
						$value = $client['Client']['client_name'];
					}
					else {
						$value = '';
					}
					echo $this->Form->input('search_clients', array(
						'label' => false,
						'placeholder' => 'Search Clients',
						'value' => $value
					));
				?>
			</div>
			<?php echo $this->Form->create(null, array(
				'class' => 'clearfix form-inline',
				'type' => 'get',
				'url' => array(
					'controller' => 'projects',
					'action' => 'index',
				),
			));?>
			<?php echo $this->Form->input('status', array(
				'type' => 'hidden',
				'value' => isset($this->params->query['status']) ? $this->params->query['status'] : null
			)); ?>
			<?php echo $this->Form->input('group_id', array(
				'type' => 'hidden',
				'value' => isset($this->params->query['group_id']) ? $this->params->query['group_id'] : null)
			); ?>
			<?php echo $this->Form->input('admin_id', array(
				'type' => 'hidden',
				'value' => isset($this->params->query['admin_id']) ? $this->params->query['admin_id'] : null)
			); ?>
			<?php if (isset($client)): ?>
				<?php echo $this->Form->input('client_id', array(
					'type' => 'hidden',
					'value' => $client['Client']['id']
				)); ?>
			<?php endif; ?>
			<?php echo $this->Form->submit('Filter By Client', array('class' => 'btn btn-default btn-small')); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
	<div class="row-fluid">
		<div class="span6"><?php
			echo $this->Html->link(
				'All Projects', 
				array(
					'action' => 'index', 
					'?' => array(
						'status' => 'all',
						'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null,
						'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
						'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
					)
				), 
				array(
					'class' => 'btn btn-' . ($status_filter == 'all' ? 'primary' : 'default')
				)
			);
			?> <?php 
			echo $this->Html->link(
				'Open', 
				array(
					'action' => 'index', 
					'?' => array(
						'status' => PROJECT_STATUS_OPEN,
						'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null,
						'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
						'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
					)
				), 
				array(
					'class' => 'btn btn-' . ($status_filter == PROJECT_STATUS_OPEN ? 'primary' : 'default')
				)
			); ?> <?php 
			echo $this->Html->link(
				'Closed', 
				array(
					'action' => 'index', 
					'?' => array(
						'status' => PROJECT_STATUS_CLOSED,
						'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null,
						'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
						'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
					)
				), 
				array(
					'class' => 'btn btn-' . ($status_filter == PROJECT_STATUS_CLOSED ? 'primary' : 'default')
				)
			); ?>  <?php 
			echo $this->Html->link(
				'Invoiced', 
				array(
					'action' => 'index', 
					'?' => array(
						'status' => PROJECT_STATUS_INVOICED,
						'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id']: null,
						'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
						'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
					)
				), 
				array(
					'class' => 'btn btn-' . ($status_filter == PROJECT_STATUS_INVOICED ? 'primary' : 'default')
				)
			); 
			?> <?php
			echo $this->Html->link(
				'Staging', 
				array(
					'action' => 'index',
					'?' => array(
						'status' => PROJECT_STATUS_STAGING,
						'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id'] : null,
						'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
						'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
					)
				), 
				array(
					'class' => 'btn btn-' . ($status_filter == PROJECT_STATUS_STAGING ? 'primary' : 'default')
				)
			);
		?>
		<?php if (isset($this->request->query['group_id']) && in_array($this->request->query['group_id'], array(3, 4))): ?>
			 <?php
				echo $this->Html->link(
					'Sampling', 
					array(
						'action' => 'index',
						'?' => array(
							'status' => PROJECT_STATUS_SAMPLING,
							'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id'] : null,
							'admin_id' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id']: null,
							'client_id' => isset($this->request->query['client_id']) ? $this->request->query['client_id']: null
						)
					), 
					array(
						'class' => 'btn btn-' . ($status_filter == PROJECT_STATUS_SAMPLING ? 'primary' : 'default')
					)
				);
			?>
		<?php endif; ?>	
		</div>
		<div class="span3">
			<?php
			echo $this->Form->create(null, array(
				'class' => 'clearfix form-inline',
				'type' => 'get',
				'url' => array(
					'controller' => 'projects',
					'action' => 'index',
				),
			));
			?>
			<?php echo $this->Form->input('status', array(
				'type' => 'hidden', 
				'value' => isset($this->params->query['status']) ? $this->params->query['status'] : null
			)); ?>
			<?php echo $this->Form->input('group_id', array(
				'type' => 'hidden', 
				'value' => isset($this->params->query['group_id']) ? $this->params->query['group_id'] : null)
			); ?>
			<?php if (isset($this->request->query['client_id']) && !empty($this->request->query['client_id'])): ?>
				<?php echo $this->Form->input('client_id', array(
					'type' => 'hidden',
					'value' => $this->request->query['client_id']
				)); ?>
			<?php endif; ?>
			<?php if (isset($admins)): ?>
				<div class="form-group">
					<?php 
					echo $this->Form->input('admin_id', array(
						'empty' => 'All Managers',
						'label' => false,
						'options' => $admins,
						'value' => isset($this->request->query['admin_id']) ? $this->request->query['admin_id'] : null
					));	
					?>
					<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
				</div>
			<?php endif; ?>
			<?php echo $this->Form->end(null); ?>
		</div>
		<div class="span3">
			<?php
			echo $this->Form->create(null, array(
				'class' => 'clearfix form-inline',
				'type' => 'get',
				'url' => array(
					'controller' => 'projects',
					'action' => 'index',
				),
			));
			?>
			<?php echo $this->Form->input('status', array(
				'type' => 'hidden', 
				'value' => isset($this->params->query['status']) ? $this->params->query['status'] : '')
			);?>
			<?php if (isset($this->request->query['client_id']) && !empty($this->request->query['client_id'])): ?>
				<?php echo $this->Form->input('client_id', array(
					'type' => 'hidden',
					'value' => $this->request->query['client_id']
				)); ?>
			<?php endif; ?>
			<?php if (isset($this->request->query['admin_id']) && !empty($this->request->query['admin_id'])): ?>
				<?php echo $this->Form->input('admin_id', array(
					'type' => 'hidden',
					'value' => $this->request->query['admin_id']
				)); ?>
			<?php endif; ?>
			<?php echo $this->element('user_groups'); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
	
	<?php $sf_class = ($status_filter == PROJECT_STATUS_INVOICED ? 'bg-gray' : 'bg-lgray'); ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
			<thead class="header">
				<tr>
					<td></td>
					<td>Project</td>
					<td>Date</td>
					<td>Partners</td>
					<td>Client Rate</td>
					<?php if(isset($group) && $group['Group']['calculate_margin']): ?>
						<td>Margin</td>
					<?php endif; ?>	
					<td><?php echo $this->Paginator->sort('bid_ir', 'IR'); ?> / <?php echo $this->Paginator->sort('SurveyVisitCache.client_ir', 'Client'); ?> / <?php echo $this->Paginator->sort('SurveyVisitCache.ir', 'Actual'); ?></td>
					<td><?php echo $this->Paginator->sort('SurveyVisitCache.drops', 'Drops'); ?></td>
					<td><?php echo $this->Paginator->sort('epc', 'EPC', array('direction' => 'desc')); ?> (<?php echo $this->Paginator->sort('SurveyVisitCache.epc', 'Actual'); ?>)</td>
					<td>LOI</td>
					<td>Quota</td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Complete">C</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Click">CL</span></td>
					<?php if (isset($group) && $group['Group']['key'] == 'socialglimpz'): ?>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Socialglimpz Rejects">RJ</span></td>
					<?php endif; ?>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Overquota">OQ</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Overquota">OQ-I</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Disqualify">NQ</span></td>
					<td class="<?php echo $sf_class; ?>"><span class="tt" data-toggle="tooltip" title="Speeding">S</span></td>
					<td class="<?php echo $sf_class; ?>"><span class="tt" data-toggle="tooltip" title="Fraudulent">F</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Prescreener Click">P-CL</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Prescreener Complete">P-C</span></td>
					<td class="bg-gray"><span class="tt" data-toggle="tooltip" title="Prescrener Disqualify">P-NQ</span></td>
					<td></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($projects as $project): ?>
					<tr class="<?php echo ($project['Project']['active']) ? 'live': 'paused'; ?>">
						<td class="id">
							<?php $icon_ok = ($project['Project']['status'] == PROJECT_STATUS_INVOICED) ? ' <i class="icon-ok"></i>' : ''; ?>
							<?php
								$id = $this->App->project_id($project); 
							?>
							<?php echo $this->Html->link($icon_ok.' '.$id, 
								array(
									'controller' => 'surveys', 
									'action' => 'dashboard', 
									$project['Project']['id'], 
									'?' => array(
										'group_id' => isset($this->request->query['group_id']) ? $this->request->query['group_id'] : null
									)
								), 
								array(
									'class' => 'btn btn-sm btn-default', 
									'escape' => false
								)
							); ?>
						</td>						
						<td>
							<?php if (!empty($project['ProjectOption'])): ?>	
								<?php foreach ($project['ProjectOption'] as $key => $project_option): ?>
									<?php $project['ProjectOption'][$project_option['name']] = $project_option['value']; ?>
									<?php unset($project['ProjectOption'][$key]); ?>
								<?php endforeach; ?>
								<?php if (!empty($project['ProjectOption']['links.count'])): ?>
									<i class="icon-flag"></i>
								<?php endif;?>
							<?php endif; ?>						
							<?php echo $project['Project']['prj_name']; ?>
							<?php if ($current_user['AdminRole']['admin'] == true && !empty($project['Admin']['id'])): ?>
								<span class="label label-info">Partner</span>
							<?php endif; ?>
							<?php if (!empty($project['Project']['country'])): ?>			
								<?php echo $this->Html->image('/img/flags/' . strtolower($project['Project']['country']) . '.png'); ?> 
							<?php endif; ?>
							<?php if (!empty($project['Project']['recontact_id'])): ?>
								 <i class="icon-screenshot" title="Recontact #<?php echo $project['Project']['recontact_id']; ?>"></i>
							<?php endif; ?>
							<div class="muted">
								<?php if (isset($group) && $group['Group']['key'] == 'mintvine' && $project['Project']['temp_qualifications'] == 1): ?>
									<span class="label label-green">QQQ</span>
								<?php endif; ?>
								<?php if (!empty($project['FedSurvey']['survey_type'])): ?>
									<span class="label label-green"><?php echo $project['FedSurvey']['survey_type'] ?></span>
								<?php endif; ?>
								<?php if ($project['Project']['qualifications_match']): ?>
									<span class="label label-green">Match</span>
								<?php endif; ?>
								<?php if ($project['FedSurvey']['direct']): ?>
									<span class="label label-green">Direct</span>
								<?php endif; ?>
								<?php echo $project['Client']['client_name'];?>
								
								<?php if (!empty($project['FedSurvey']['total']) && $project['FedSurvey']['total'] != FED_MAGIC_NUMBER): ?>
								- Matched <?php echo number_format($project['FedSurvey']['total']); ?> users
								<?php endif; ?>
								
								<?php if (!empty($project['ProjectOption'])): ?>
									<?php if (isset($project['ProjectOption']['fulcrum.floor'])): ?>
										<span class="label label-warning">Floor</span>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</td>
						<td class="nowrap">
							<?php echo $this->Time->format($project['Project']['date_created'], '%h %e', false, 'America/Los_Angeles'); ?>
						</td>
						<td>
							<?php if ($project['Project']['partner_count']): ?>
								<?php echo $this->Html->link($project['Project']['partner_count'], '#', array(
									'onclick' => 'return MintVine.ShowPartners(this, '.$project['Project']['id'].')',
								)); ?>
							<?php else: ?>
							<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td><?php echo $this->App->dollarize($project['Project']['client_rate'], 2); ?></td>
						<?php if (isset($group) && $group['Group']['calculate_margin']): ?>
							<td><?php echo (is_null($project['Project']['margin_pct'])) ? '<span class="muted">-</span>' : $project['Project']['margin_pct'].'%'; ?></td>
						<?php endif; ?></td>
						<td class="nowrap"><?php echo $this->App->ir($project); ?></td>
						<td class="nowrap"><?php echo $this->App->drops($project); ?></td>
						<td class="nowrap"><?php echo $this->App->epc($project); ?></td>
						<td class="nowrap"><?php echo $project['Project']['est_length']; ?>  / 
							<?php if (!empty($project['SurveyVisitCache']['loi_seconds'])) : ?>
								<?php echo round($project['SurveyVisitCache']['loi_seconds'] / 60); ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?></td>
						<td><?php echo $this->App->quota_number($project); ?></td>
						<td class="bg-gray">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['complete']; ?> Completes">
								<?php echo $project['SurveyVisitCache']['complete']; ?>
							</span>
						</td>
						<td class="bg-gray">
							<?php if (!empty($project['SurveyVisitCache']['click'])): ?>
								<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['click']; ?> Clicks">
									<?php echo $project['SurveyVisitCache']['click']; ?>
								</span>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<?php if ($project['Group']['key'] == 'socialglimpz'): ?>
						<td class="bg-gray">
							<span class="tt" data-toggle="tooltip" title="<?php echo isset($project['Project']['socialglimpz_rejects']) ? $project['Project']['socialglimpz_rejects'] . ' Rejects' : ''; ?> ">
								<?php echo isset($project['Project']['socialglimpz_rejects']) ? $project['Project']['socialglimpz_rejects'] : '-';?>
							</span>
						</td>
						<?php endif; ?>
						<td class="bg-gray">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['overquota']; ?> Overquota">
								<?php echo $project['SurveyVisitCache']['overquota']; ?>
							</span>
						</td>
						<td class="bg-gray">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['oq_internal']; ?> Overquota Internal">
								<?php echo $project['SurveyVisitCache']['oq_internal']; ?>
							</span>
						</td>
						<td class="bg-gray">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['nq']; ?> Disqualify">
								<?php echo $project['SurveyVisitCache']['nq']; ?>
							</span>
						</td>

						<td class="<?php echo $sf_class; ?>">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['speed']; ?> Speeding">
								<?php echo $project['SurveyVisitCache']['speed']; ?>
							</span>
						</td>
						<td class="<?php echo $sf_class; ?>">
							<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['fraud']; ?> Fraudulent">
								<?php echo $project['SurveyVisitCache']['fraud']; ?>
							</span>
						</td>
						<td class="bg-gray">
							<?php if ($project['Project']['prescreen']): ?>
								<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['prescreen_clicks']; ?> Prescreener Clicks">
									<?php echo $project['SurveyVisitCache']['prescreen_clicks']; ?>
								</span>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td class="bg-gray">
							<?php if ($project['Project']['prescreen']): ?>
								<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['prescreen_completes']; ?> Prescreener Complete">
									<?php echo $project['SurveyVisitCache']['prescreen_completes']; ?>
								</span>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td class="bg-gray">
							<?php if ($project['Project']['prescreen']): ?>
								<span class="tt" data-toggle="tooltip" title="<?php echo $project['SurveyVisitCache']['prescreen_nqs']; ?> Prescreener Disqualify">
									<?php echo $project['SurveyVisitCache']['prescreen_nqs']; ?>
								</span>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td class="nowrap">
							<?php 
								$disable_launch = '';
								if ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED) {
									$disable_launch = ' disabled';
								}
								echo $this->Html->link(
								'<span class="'.Utils::survey_status($project, 'icon').'"></span>', 
								'#', 
								array(
									'class' => 'btn btn-sm '.Utils::survey_status($project, 'button').$disable_launch,
									'escape' => false,
									'onclick' => ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED) ? '' : 'return MintVine.PauseProject(this, '.$project['Project']['id'].')'
								)
							); ?> 
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<p class="pull-right"><?php 
	echo $this->Html->link('Old Projects View', array('controller' => 'surveys', 'action' => 'index')); 
?></p> 
<?php echo $this->Element('pagination'); ?>

<?php echo $this->Element('modal_project_status'); ?>
<?php 
	// echo $this->Element('sql_dump');
?>
