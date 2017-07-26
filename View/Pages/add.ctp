<?php $this->Html->script('//tinymce.cachefly.net/4.0/tinymce.min.js', array('inline' => false)); ?>
<script type="text/javascript">
tinymce.init({
    selector: "textarea"
 });
</script>
<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create New Page</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span8">
				<div class="padded">
					<?php echo $this->Form->input('title', array('label' => 'Title'));?>
					<?php echo $this->Form->input('body', array('rows' => '10', 'cols' => '10', 'label' => 'Body'));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>