<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title>MintVine
		<?php echo $title_for_layout; ?>
	</title>
	<?php
		echo $this->Html->css('/assets/stylesheets/application');
		echo $this->Html->css('/css/styles.css?date=05122017');
		echo $this->Html->css('/css/jquery-ui.css?date=20062016');
		
		echo $this->Html->script('/js/jquery-1.10.2.min'); 
		echo $this->Html->script('/js/scripts.js?date=06162017'); 
		echo $this->Html->script('/assets/javascripts/application');
		echo $this->Html->script('/js/jquery.scrollTo'); 
		echo $this->Html->meta('icon');
		
		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
	?>
</head>
<body>
	<div class="navbar navbar-top navbar-inverse">
		<div class="navbar-inner">
			<div class="container-fluid">
				<a class="brand" href="/">MintVine</a>
				<?php if ($current_user): ?>
					<?php $role_transactions = ($current_user['AdminRole']['transactions'] == true); ?>
					<?php $role_guest = ($current_user['AdminRole']['guest'] == true); ?>
					<?php $user_roles = array();
						foreach ($current_user['AdminRole'] as $key => $roles) {
							if ($roles) {
								$user_roles[] = $key;
							}
						}
					?>
					<div class="pull-right cp_navigation">
						<ul class="nav pull-right">
							<?php foreach ($admin_menu as $key => $menu): ?>
								<?php if (count(array_intersect($menu['allowed_roles'], $user_roles)) > 0): ?>
									<li <?php echo $controller == $key ? 'class="active dropdown"': 'class="dropdown"'; ?>>
										<?php if (isset($menu['children']) && !empty($menu['children'])): ?>
											<a href="#" class="dropdown-toggle" data-toggle="dropdown">
												<?php echo (isset($menu['icon_class']) && !empty($menu['icon_class'])) ? '<i class="'.$menu['icon_class'].'"></i>' : ''; ?>
												<?php echo $menu['name']; ?> <b class="caret"></b>
											</a>
											<ul class="dropdown-menu">
												<?php foreach ($menu['children'] as $child): ?>
													<?php if (isset($child['divider']) && $child['divider'] && count(array_intersect($child['allowed_roles'], $user_roles)) > 0): ?>
														<li class="divider"></li>
													<?php endif; ?>
													<li>
														<?php
															$icon_class = '';
															if (isset($child['add']) && !empty($child['add'])) {
																$icon_class = 'left_item';
															}
															if (count(array_intersect($child['allowed_roles'], $user_roles)) > 0) {
																echo $this->Html->link($child['name'], $child['url'], array('class' => $icon_class));
																// add button
																if (isset($child['add']) && !empty($child['add'])) {
																	echo $this->Html->link('<i class="icon-plus-sign"></i>', $child['add']['url'], array('class' => 'plus_icon', 'escape' => false));
																}
															}
														?>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php else: ?>
											<?php echo $this->Html->link($menu['name'], $menu['url']); ?>
										<?php endif; ?>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
							<li class="dropdown">
								<a href="#" class="dropdown-toggle" data-toggle="dropdown"> <?php echo $current_user['Admin']['admin_user']; ?> <b class="caret"></b></a>
								<ul class="dropdown-menu">
									<?php if ($role_guest): ?>
										<?php $urls = json_decode($current_user['Admin']['limit_access'], true); ?>
										<?php if (!empty($urls)): ?>
											<?php foreach($urls as $url): ?>
												<?php if (strpos($url, 'ajax_') !== false): ?>
													<?php continue; ?>
												<?php endif; ?>
												<li><?php 
													echo $this->Html->link($url, '/'.$url);
												?></li>
											<?php endforeach; ?>	
										<?php endif; ?>
									<?php endif; ?>
									<li><?php 
										echo $this->Html->link('Preferences', array('controller' => 'admins', 'action' => 'preferences'));
									?></li>
									<li><?php 
										echo $this->Html->link('Logout', array('controller' => 'admins', 'action' => 'logout'));
									?></li>
								</ul>
							</li>
						</ul>
					</div>
					<?php echo $this->Form->create(false, array(
						'type' => 'get',
						'class' => 'navbar-form navbar-right pull-left project-search',
						'url' => array('controller' => 'projects', 'action' => 'index', 'named' => array())
					)); ?>
					<?php echo $this->Form->input('q', array(
						'placeholder' => 'Project Search',
						'label' => false,
						'value' => null,
						'div' => false,
						'name' => 'q'
					)); ?>			
					<?php echo $this->Form->input('status', array('type' => 'hidden', 'value' => isset($this->params->query['status']) ? $this->params->query['status'] : null)); ?>
					<?php echo $this->Form->input('group_id', array('type' => 'hidden', 'value' => isset($this->params->query['group_id']) ? $this->params->query['group_id'] : null)); ?>

					<?php echo $this->Form->end(null); ?>

					<?php if ($role_transactions): ?>
						<?php echo $this->Form->create(false, array(
							'type' => 'get',
							'class' => 'navbar-form navbar-right pull-left project-search',
							'style' => 'margin-left: 8px;',
							'url' => array('controller' => 'users', 'action' => 'index', 'named' => array())
						)); ?>
						<?php echo $this->Form->input('keyword', array(
							'placeholder' => 'User Search',
							'label' => false,
							'value' => null,
							'div' => false,
							'name' => 'keyword'
						)); ?>
						<?php echo $this->Form->end(null); ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="container-fluid">
		<div class="row-fluid padded">
			<?php echo $this->Session->flash(); ?>
			<?php echo $this->fetch('content'); ?>
		</div>
		<div class="row-fluid padded">
			<?php if (defined('SERVER_HOSTNAME')): ?>
				<div style="text-align: center;">
					<small>Server: <?php echo SERVER_HOSTNAME; ?></small>
				</div>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>
