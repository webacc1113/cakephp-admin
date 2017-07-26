<?php
echo $this->Form->input('zipcode', array('required' => true));
?>

<?php
echo $this->Form->input('state_abbr', array(
	'value' => $zip_code['LucidZip']['state_abbr'],
));
?>

<?php
echo $this->Form->input('state_full', array(
	'value' => $zip_code['LucidZip']['state_full'],
));
?>

<?php
echo $this->Form->input('area_code', array(
	'value' => $zip_code['LucidZip']['area_code'],
));
?>

<?php
echo $this->Form->input('city', array(
	'value' => $zip_code['LucidZip']['city'],
));
?>

<?php
echo $this->Form->input('population', array(
	'value' => $zip_code['LucidZip']['population'],
));
?>

<?php
echo $this->Form->input('county', array(
	'value' => $zip_code['LucidZip']['county'],
));
?>

<?php
echo $this->Form->input('county_fips', array(
	'value' => $zip_code['LucidZip']['county_fips'],
));
?>

<?php
echo $this->Form->input('state_fips', array(
	'value' => $zip_code['LucidZip']['state_fips'],
));
?>

<?php
echo $this->Form->input('timezone', array(
	'value' => $zip_code['LucidZip']['timezone'],
));
?>

<?php
echo $this->Form->input('daylight', array(
	'value' => $zip_code['LucidZip']['daylight'],
));
?>

<?php
echo $this->Form->input('msa', array(
	'value' => $zip_code['LucidZip']['msa'],
));
?>

<?php
echo $this->Form->input('pmsa', array(
	'value' => $zip_code['LucidZip']['pmsa'],
));
?>

<?php
echo $this->Form->input('csa', array(
	'value' => $zip_code['LucidZip']['csa'],
));
?>

<?php
echo $this->Form->input('dma', array(
	'value' => $zip_code['LucidZip']['dma'],
));
?>

<?php
echo $this->Form->input('dma_name', array(
	'value' => $zip_code['LucidZip']['dma_name'],
));
?>

<?php
echo $this->Form->input('dma_rank', array(
	'value' => $zip_code['LucidZip']['dma_rank'],
));
?>

<?php
echo $this->Form->input('cbsa', array(
	'value' => $zip_code['LucidZip']['cbsa'],
));
?>

<?php
echo $this->Form->input('cbsa_type', array(
	'value' => $zip_code['LucidZip']['cbsa_type'],
));
?>

<?php
echo $this->Form->input('cbsa_name', array(
	'value' => $zip_code['LucidZip']['cbsa_name'],
));
?>

<?php
echo $this->Form->input('msa_name', array(
	'value' => $zip_code['LucidZip']['msa_name'],
));
?>

<?php
echo $this->Form->input('pmsa_name', array(
	'value' => $zip_code['LucidZip']['pmsa_name'],
));
?>

<?php
echo $this->Form->input('region', array(
	'value' => $zip_code['LucidZip']['region'],
));
?>

<?php
echo $this->Form->input('division', array(
	'value' => $zip_code['LucidZip']['division'],
));
?>

<?php
echo $this->Form->input('csa_name', array(
	'value' => $zip_code['LucidZip']['csa_name'],
));
?>

<?php
echo $this->Form->input('csa_div_name', array(
	'value' => $zip_code['LucidZip']['csa_div_name'],
));
?>