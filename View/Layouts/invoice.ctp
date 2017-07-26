<!DOCTYPE html>
<html lang="en">
	<head>
		<?php echo $this->Html->charset(); ?>
		<?php echo $this->Html->meta('icon'); ?>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="">
		<meta name="author" content="">
		<title>BRINC Invoice</title>
		<style>
			*, *:before, *:after {
				box-sizing: border-box;
			}
			.container:before, .container:after {
				content: " ";
				display: table;
			}
			.container:after {
				clear: both;
			}
			.container{
				max-width: 1170px;
				margin-left: auto;
				margin-right: auto;
				margin-top: 20px;
				margin-bottom: 20px;
				padding-right: 50px;
				padding-left: 50px;
			}
			b, strong {
				font-weight: bold;
			}
			h1, .h1 {
				font-size: 36px;
				margin-top: 0px;
				margin-bottom: 10px;
			}
			h2, h3 {
				margin-bottom: 10px;
				margin-top: 20px;
			}
			h5, .h5 {
				font-size: 14px;
			}
			h4, h5, h6 {
				margin-bottom: 10px;
				margin-top: 10px;
			}
			h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6 {
				font-family: "Exo 2", Helvetica, Arial, sans-serif; 
				line-height: 1;
				color: #475059;
				font-weight: 500;
			}
			p {
				margin: 0 0 10px;
			}
			img {
				vertical-align: middle;
				border: 0 none;
			}
			.h1-title {
				text-align:right;
				float: right;
			}
			.table {
				margin-bottom: 20px;
				width: 100%;
			}
			table {
				background-color: rgba(0, 0, 0, 0);
				max-width: 100%;
				border-collapse: collapse;
				border-spacing: 0;
			}
			.table-condensed thead > tr > th, .table-condensed tbody > tr > th, .table-condensed tfoot > tr > th, .table-condensed thead > tr > td, .table-condensed tbody > tr > td, .table-condensed tfoot > tr > td {
				padding: 5px;
			}
			.table thead > tr > th, .table tbody > tr > th, .table tfoot > tr > th, .table thead > tr > td, .table tbody > tr > td, .table tfoot > tr > td {
				border-top: 1px solid #DDDDDD;
				line-height: 1.42857;
				padding: 8px;
				vertical-align: top;
			}
			th {
				text-align: left;
			}
			body {
				font-family: "Lato", Helvetica, Arial, sans-serif;
				font-size: 13px;
				line-height: 1;
				color: #475059;
				background-color: white;
			}
			.t-right{
				text-align: right;
			}
			hr {
				border-color: #EEEEEE;
				border-style: solid none none;
				border-width: 1px 0 0;
				margin-bottom: 20px;
				margin-top: 20px;
			}
			.to {
				margin-bottom: 7px;
			}
			.address {
				line-height:24px;
				margin-bottom: 5px;
			}
			
		</style>
		<link href='http://fonts.googleapis.com/css?family=Lato:400,700|Exo+2:400,700' rel='stylesheet' type='text/css'>
	</head>
	<body>
		<?php echo $this->fetch('content'); ?>
	</body>
</html>
