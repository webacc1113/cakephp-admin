<h4>Datapoints</h4>
<?php if (!empty($parent_qualifications)): ?>
	<dl>
		<?php foreach ($parent_qualifications as $qualification): ?>
			<dt><?php echo $qualification['RfgQuestion']['question']; ?></dt>
			<?php if (!empty($qualification['RfgAnswer'])): ?>
				<dd>
					<?php foreach ($qualification['RfgAnswer'] as $answer): ?>
						<?php echo $answer['answer']; ?><br />
					<?php endforeach; ?>
				</dd>
			<?php endif; ?>
		<?php endforeach; ?>
	</dl>
<?php endif; ?>

<?php if (!empty($quotas)): ?>
	<h4>Quotas</h4>
	<?php foreach ($quotas as $key => $quota): ?>
		<?php if (!empty($quota)): ?>
			<h5>Qualification for Quota #: <?php echo $key + 1; ?></h5>
			<dl>
				<?php foreach ($quota as $qualification): ?>
				<dt><?php echo $qualification['RfgQuestion']['question']; ?></dt>
					<?php if (!empty($qualification['RfgAnswer'])): ?>
						<dd>
							<?php foreach ($qualification['RfgAnswer'] as $answer): ?>
								<?php echo $answer['answer']; ?><br />
							<?php endforeach; ?>
						</dd>
					<?php endif; ?>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>
