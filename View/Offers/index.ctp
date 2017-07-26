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

<h3>Offers</h3>

<p><?php echo $this->Html->link('New Offer', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<?php echo $this->Form->create('Offer'); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($offers)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td><?php echo $this->Paginator->sort('Offer.offer_title', 'Title'); ?></td>
				<td><?php echo $this->Paginator->sort('Offer.offer_desc', 'Description'); ?></td>
				<td><?php echo $this->Paginator->sort('Offer.client_rate', 'Client Rate'); ?></td>
				<td><?php echo $this->Paginator->sort('Offer.award', 'Award'); ?></td>
				<td><?php echo $this->Paginator->sort('Offer.active', 'Active?'); ?></td>
				<td><?php echo $this->Paginator->sort('Offer.paid', 'Paid?'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($offers as $offer): ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('Offer.' . $offer['Offer']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td>
						<?php echo $offer['Offer']['offer_title'];?>
					</td>
					<td>
						<?php echo $this->Text->truncate($offer['Offer']['offer_desc'], 80); ?>
					</td>
					<td>
						<?php echo $this->App->dollarize($offer['Offer']['client_rate'], 2); ?>
					</td>
					<td>
						<?php echo $offer['Offer']['award']; ?> pts
					</td>
					<td>
						<?php if ($offer['Offer']['active']):?>
							<?php echo $this->Html->link('Active', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.ActiveOffer('.$offer['Offer']['id'].', this)')); ?>
						<?php else:?>
							<?php echo $this->Html->link('Inactive', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.ActiveOffer('.$offer['Offer']['id'].', this)')); ?>
						<?php endif;?>
					</td>
					<td>
						<?php echo ($offer['Offer']['paid']) ? 'Yes' : 'No'; ?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $offer['Offer']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php if (!empty($offers)): ?>
			<?php echo $this->Form->submit('Delete', array(
				'name' => 'delete',
				'class' => 'btn btn-danger',
				'rel' => 'tooltip',
				'data-original-title' => 'Clicking this button will delete the selected records, This is IRREVERSIBLE.',
			));
			?>
		<?php endif; ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>