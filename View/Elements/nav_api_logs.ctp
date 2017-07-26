<p><?php 
	echo $this->Html->link('Cint', array(
		'controller' => 'cint_logs',
		'action' => 'index',
	), array(
		'div' => false,
		'class' => 'btn '.($nav == 'cint' ? 'btn-primary': 'btn-default'),
	));
?> <?php 
	echo $this->Html->link('Toluna', array(
		'controller' => 'toluna_logs',
		'action' => 'index',
	), array(
		'div' => false,
		'class' => 'btn '.($nav == 'toluna' ? 'btn-primary': 'btn-default'),
	));
?> <?php 
	echo $this->Html->link('Precision', array(
		'controller' => 'precision_logs',
		'action' => 'index',
	), array(
		'div' => false,
		'class' => 'btn '.($nav == 'precision' ? 'btn-primary': 'btn-default'),
	));
?> <?php 
	echo $this->Html->link('Points2Shop', array(
		'controller' => 'points2shop_logs',
		'action' => 'index',
	), array(
		'div' => false,
		'class' => 'btn '.($nav == 'points2shop' ? 'btn-primary': 'btn-default'),
	));
?></p>