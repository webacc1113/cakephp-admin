<?php if (empty($current_user['Admin']['timezone'])): ?>
	<?php echo $this->Html->script('moment.min'); ?>
	<script type="text/javascript">
		var offset = moment().zone();
		MintVine.SaveUserTimezone(offset * -1); //we multiply offset with -1, because moment.js always return the offset inverse for some reason. so e.g for +5, moment.js return -300.
	</script>
<?php endif; ?>
<?php if ($current_user['AdminRole']['guest']): ?>
	<div class="box">
		<div class="box-content">
			<div class="padded">
				You can access the following URLs.
				<p>&nbsp;</p>
				<?php $urls = json_decode($current_user['Admin']['limit_access'], true); ?>
				<?php if (!empty($urls)): ?>
					<ul>
						<?php foreach($urls as $url): ?>
							<?php if (strpos($url, 'ajax_') !== false): ?>
								<?php continue; ?>
							<?php endif; ?>
							<li><?php 
								echo $this->Html->link($url, '/'.$url);
							?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php else: ?>	
	<div class="box">
		<div class="area-top clearfix">
			<div class="pull-left header">
				<h3 class="title"><i class="icon-dashboard"></i>MintVine Control Panel</h3>
			</div>

			<ul class="inline pull-right sparkline-box">
				<li class="sparkline-row" style="width: 120px;">
					<h4 class="blue"><span>Total Users</span> <?php echo number_format($user_total_count); ?></h4>
				</li>
				<li class="sparkline-row" style="width: 120px;">
					<h4 class="green"><span>New Users</span> <?php echo number_format($user_verified); ?></h4>
				</li>
				<li class="sparkline-row" style="width: 120px;">
					<h4 class="red"><span>Active Projects</span> <?php echo number_format($project_count); ?></h4>
				</li>
			</ul>
		</div>
		
		<div class="box-content">
			<div class="padded">
				<div class="row-fluid">
					<div class="span3">
						<div class="action-nav-normal">
							<div class="row-fluid">
								<div class="span6 action-nav-button">
									<a href="/surveys/add/<?php echo $mintvine_group['Group']['id']; ?>" title="New Project">
										<i class="icon-file-alt"></i>
										<span>New Project</span>
									</a>
									<span class="triangle-button red"><i class="icon-plus"></i></span>
								</div>
								<div class="span6 action-nav-button">
									<a href="/reports/generate" title="New Report">
										<i class="icon-folder-open-alt"></i>
										<span>Generate Report</span>
									</a>
									<span class="triangle-button red"><i class="icon-plus"></i></span>
								</div>
							</div>
						</div>
						
						<div class="row-fluid">						
							<?php echo $this->Html->link(
								'Mail Queue',
								array('controller' => 'statistics', 'action' => 'ajax_mail_queue'), 
								array(
									'data-target' => '#modal-mail-queue',
									'data-toggle' => 'modal', 
									'class' => 'btn btn-default'
								)
							); ?> 
							<?php echo $this->Element('modal_mail-queue'); ?>
							<?php echo $this->Html->link(
								'Pooled Points',
								array('controller' => 'users', 'action' => 'ajax_pooled_points'), 
								array(
									'data-target' => '#modal-pooled-points',
									'data-toggle' => 'modal', 
									'class' => 'btn btn-default'
								)
							); ?> 
							<?php echo $this->Element('modal_pooled_points'); ?>
						</div>
						
						<div class="row-fluid">
							<div class="span12">
								<?php if ($current_user['AdminRole']['admin']): ?>
									<?php echo $this->Html->link(
										'Payment Logs', array('controller' => 'payment_logs', 'action' => 'index'), array(
										'class' => 'btn btn-default slide-down-15'
									)); ?> 
								<?php endif; ?>
								<?php
								if (!empty($quickbook_connect_status) && ($quickbook_connect_status == QUICKBOOK_OAUTH_NOT_CONNECTED || $quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRING_SOON || $quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRED)) :
									?>
									<script type="text/javascript" src="https://appcenter.intuit.com/Content/IA/intuit.ipp.anywhere.js"></script>
									<script>intuit.ipp.anywhere.setup({
										menuProxy: '',
										grantUrl: '<?php echo Router::url(array('controller' => 'quick_books', 'action' => 'request_oauth_token'), true) ?>'});
									</script>
									<ipp:connectToIntuit></ipp:connectToIntuit>
									<?php
								else :
									echo '<br />' . $this->Html->link(
											'Connected to QuickBook', 'javascript:void(0)', array(
										'class' => 'btn btn-success'
											)
									);
								endif;
								?>
							</div>
						</div>
					</div>
					<div class="span9">
						<div class="row-fluid">
							<div class="box">
								<div class="box-header">
									<span class="title">Quick Project Search</span>
								</div>
								<div class="box-content">
									<div class="padded">
										<?php echo $this->Form->create(null, array(
											'class' => 'clearfix form-inline',
											'type' => 'get',
											'url' => array(
												'controller' => 'surveys',
												'action' => 'index', 
												'named' => array()
											)
										)); ?>

										<?php echo $this->Form->input('status', array(
											'type' => 'hidden',
											'value' => 'Open'
										)); ?>
										<div class="form-group"><?php echo $this->Form->input('q', array(
											'label' => false,
											'label' => 'Project # or name',
											'style' => 'font-size: 24px; padding: 8px;',
										)); ?></div>

										<div class="form-group"><label>&nbsp;</label><?php 
											echo $this->Form->submit('Find Project', array('class'=> 'btn btn-large btn-default')); 
										?></div>
										<?php echo $this->Form->end(null); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="row-fluid">
							<div class="box">
								<div class="box-header">
									<span class="title">Quick User Search</span>
								</div>
								<div class="box-content">
									<div class="padded">
										<?php echo $this->Form->create(null, array(
											'class' => 'clearfix form-inline',
											'type' => 'get',
											'url' => array(
												'controller' => 'users',
												'action' => 'index', 
												'named' => array()
											)
										)); ?>

										<div class="form-group"><?php echo $this->Form->input('keyword', array(
											'label' => false,
											'label' => 'User # or keyword search',
											'style' => 'font-size: 24px; padding: 8px;',
										)); ?></div>

										<div class="form-group"><label>&nbsp;</label><?php 
											echo $this->Form->submit('Find User', array('class'=> 'btn btn-large btn-default')); 
										?></div>
										<?php echo $this->Form->end(null); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>