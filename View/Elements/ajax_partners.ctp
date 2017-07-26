<?php if ($partners): ?>
	<?php foreach ($partners as $partner): ?>
		<tr class="partner<?php echo $project_id?> <?php echo $status; ?>">
			<td>Partner</td>
			<td><?php echo $partner['Partner']['partner_name']; ?></td>
			<td></td>
			<td></td>
			<td>$<?php echo $partner['rate'] ?></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['completes']; ?> Completes">
					<?php echo $partner['completes']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['clicks']; ?> Clicks">
					<?php echo $partner['clicks']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['oqs']; ?> Overquota">
					<?php echo $partner['oqs']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['oqs']; ?> Overquota Internal">
					<?php echo $partner['oqs_internal']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['nqs']; ?> Disqualify">
					<?php echo $partner['nqs']; ?>
				</span>
			</td>
			<td class="bg-lgray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['speeds']; ?> Speeding">
					<?php echo $partner['speeds']; ?>
				</span>
			</td>
			<td class="bg-lgray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['fails']; ?> Fails">
					<?php echo $partner['fails']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['prescreen_clicks']; ?> Prescreener Clicks">
					<?php echo $partner['prescreen_clicks']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['prescreen_completes']; ?> Prescreener Completes">
					<?php echo $partner['prescreen_completes']; ?>
				</span>
			</td>
			<td class="bg-gray">
				<span class="tt" data-toggle="tooltip" title="<?php echo $partner['prescreen_nqs']; ?> Prescreener Disqualify">
					<?php echo $partner['prescreen_nqs']; ?>
				</span>
			</td>
			<td>
				<?php if ($current_user['AdminRole']['admin'] == true) : ?>
					<?php if (!$partner['paused']) : ?>
						<?php echo $this->Html->link('<span class="icon-play"></span>', '#', array(
							'class' => 'btn btn-sm btn-success',
							'escape' => false,
							'onclick' => 'return MintVine.PausePartner(this, '.$partner['id'].');'
						)); ?>
					<?php else: ?>
						<?php echo $this->Html->link('<span class="icon-pause"></span>', '#', array(
							'class' => 'btn btn-sm btn-danger',
							'escape' => false,
							'onclick' => 'return MintVine.PausePartner(this, '.$partner['id'].');'
						)); ?>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
<?php endif; ?>