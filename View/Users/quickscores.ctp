<?php echo $this->Element('../Users/quickscore', array('user_analysis' => !empty($scores['0']) ? $scores['0'] : array()));?>
<?php echo $this->Element('../Users/scores');?>