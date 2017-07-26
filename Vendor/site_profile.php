<?php

define('USER_PROFILE_GENDERS', serialize(array(
	'M' => 'Male', 
	'F' => 'Female'
)));

define('USER_HHI', serialize(array(
	'0' => '$15,000 to $24,999',
	'1' => '$25,000 to $34,999',
	'2' => '$35,000 to $49,999',
	'3' => '$50,000 to $59,999',
	'4' => '$60,000 to $74,999',
	'5' => '$75,000 to $99,999',
	'6' => '$100,000+',
)));
define('USER_HHI_MAPPING', serialize(array(
	148, 149, 150, 5355, 5356, 153, 154
)));

define('USER_EDU', serialize(array(
	'0' => 'Student',
	'1' => 'High School Diploma',
	'2' => 'Some college',
	'3' => '2 Year Degree',
	'4' => '4 Year Degree',
	'5' => 'Master\'s Degree',
	'6' => 'PhD',
)));
define('USER_EDU_MAPPING', serialize(array(
	5396, 609, 610, 5562, 5563, 613, 614
)));

define('USER_ETHNICITY', serialize(array(
	'' => 'Prefer not to say',
	'0' => 'White/Caucasian',
	'1' => 'Black/African American',
	'2' => 'Asian',
	'3' => 'Pacific Islander',
	'4' => 'Hispanic',
	'5' => 'Other'
)));

define('USER_ORIGIN', serialize(array(
	'1' => 'Cuban',
	'2' => 'Argentina',
	'3' => 'Mexican, Mexican American, Chicano',
	'4' => 'Colombia',
	'5' => 'Equador',
	'6' => 'El Salvador',
	'7' => 'Guatemala',
	'8' => 'Nicaragua',
	'9' => 'Panama',
	'10' => 'Peru',
	'11' => 'Spain',
	'12' => 'Venezuela',
	'13' => 'Other Country',
)));

define('USER_ETHNICITY_MAPPING', serialize(array(
	630, 631, 632, 633, 634, 635
)));

define('USER_CHILDREN', serialize(array(
	'1' => 'Yes',
	'0' => 'No'
)));
define('USER_CHILDREN_MAPPING', serialize(array(
	629, 628
)));

define('USER_EMPLOYMENT', serialize(array(
	0 => 'Full Time Employee',
	1 => 'Part Time Employee',
	2 => 'Self Employed',
	3 => 'Homemaker',
	4 => 'Unemployed',
	5 => 'Student',
	6 => 'Prefer not to say',
	7 => 'Military',
	8 => 'Retired'
)));
define('USER_EMPLOYMENT_MAPPING', serialize(array(	
	5551, 5552, 5553, 5554, 616, 5556, 615, 5555, 5561
)));

define('USER_INDUSTRY', serialize(array(	
	30 => 'Advertising & Marketing',
	0 => 'Aerospace & Defense',
	1 => 'Agriculture',
	2 => 'Aviation',
	3 => 'Architecture',
	5 => 'Automobile',
	6 => 'Banking & Financial Services',
	7 => 'Biotechnology & Life Sciences',
	8 => 'Broadcasting & Cable',
	9 => 'Chemicals',
	10 => 'Computer',
	11 => 'Computer - Networking',
	12 => 'Computer - Software Development',
	51 => 'Computer - Web Development',
	13 => 'Construction',
	43 => 'Consumer Packaged Goods',
	14 => 'Consulting',
	44 => 'E-commerce',
	18 => 'Education',
	19 => 'Electronics',
	45 => 'Engineering',
	20 => 'Entertainment',
	21 => 'Financial Services & Investment',
	22 => 'Food & Beverage',
	23 => 'Government',
	15 => 'Graphic Arts',
	46 => 'Health & Fitness',
	24 => 'Healthcare',
	25 => 'Hotel & Hospitality',
	26 => 'Insurance',
	16 => 'Interior Design',
	4 => 'Legal',
	27 => 'Legal Services',
	28 => 'Real Estate',
	29 => 'Manufacturing',
	31 => 'Medical',
	47 => 'Media',
	32 => 'Military',
	33 => 'Mining',
	35 => 'Police & Fire',
	36 => 'Publishing',
	37 => 'Restaurant',
	38 => 'Retail',
	48 => 'Tax & Accounting',
	49 => 'Telecommunications',
	40 => 'Transportation',
	41 => 'Travel & Tourism',
	50 => 'Utility',
	17 => 'Warehouse',
	42 => 'Other'
)));
define('USER_INDUSTRY_MAPPING', serialize(array(
	5357, 5358, 5359, 5564, 5565, 5360, 5361, 5362, 5363, 5364, 23, 24, 25, 5365, 5366, 
	5367, 5572, 5369, 5370, 5371, 37, 43, 5372, 5373, 5566, 58, 65, 74, 120, 5376, 5377, 
	5567, 5568, 5378, 5569, 5570, 117, 5571, 126, 5590, 5380, 5381, 5417
)));

define('USER_JOB', serialize(array(
	178 => 'Account Manager',
	179 => 'Agent',
	180 => 'Analyst',
	181 => 'Assistant',
	182 => 'Assistant Manager',
	183 => 'Broker',
	184 => 'Chairman',
	9 => 'CEO',
	10 => 'CFO',
	11 => 'CIO',
	12 => 'CMO',
	13 => 'COO',
	185 => 'CTO',
	186 => 'CXO',
	23 => 'Director ',
	24 => 'Engineer',
	114 => 'Executive',
	187 => 'Executive Vice President',
	27 => 'Controller',
	155 => 'Corporate Trainer',
	29 => 'Fitness Trainer',
	30 => 'Fleet Supervisor',
	31 => 'Office Manager',
	188 => 'General Manager',
	32 => 'GM',
	33 => 'Hardware Engineer',
	44 => 'Information Systems (MIS) - Manager',
	48 => 'Investment Advisor',
	49 => 'IT/ Networking - Manager',
	189 => 'Manager',
	65 => 'Marketing Manager',
	66 => 'Mechanical Engineer',
	68 => 'Media Planning - Manager/ Executive',
	72 => 'Network Administrator',
	73 => 'Network Engineer',
	74 => 'Nurse',
	75 => 'Nutritionist',
	79 => 'Operations - Manager',
	80 => 'Ophthalmologist',
	81 => 'Optometrist',
	82 => 'Orthopaedist',
	190 => 'Owner',
	83 => 'Painter',
	88 => 'Pharmacist',
	89 => 'Photographer',
	90 => 'Physician',
	95 => 'Portfolio Manager',
	191 => 'President',
	96 => 'Private Banker',
	101 => 'Property Management',
	102 => 'Psychiatrist',
	103 => 'Psychologist',
	105 => 'Purchase Manager',
	109 => 'Radiologist',
	110 => 'Real Estate Agent',
	111 => 'Real Estate Broker',
	112 => 'Receptionist',
	133 => 'Programmer',
	115 => 'Recruiter',
	192 => 'Regional Manager',
	124 => 'Sales Executive',
	193 => 'Sales Representative',
	125 => 'Secretary',
	128 => 'Security Officer',
	194 => 'Senior Vice President',
	137 => 'Stock Broker',
	195 => 'Supervisor',
	140 => 'Surgeon',
	142 => 'System Administrator',
	145 => 'System Integrator',
	149 => 'Teacher',
	151 => 'Technician',
	154 => 'Trading Advisor',
	158 => 'Travel Agent',
	159 => 'Underwriter',
	196 => 'Vice President',
	166 => 'VP - Administration',
	173 => 'VP - Customer Service',
	162 => 'VP - Finance',
	169 => 'VP - Human Resource',
	172 => 'VP - Marketing',
	165 => 'VP - Operations',
	197 => 'VP - Sales',
	177 => 'Other'
)));
define('USER_JOB_MAPPING', serialize(array(
	159, 161, 5591, 168, 175, 185, 187, 191, 195, 5592, 5593, 5594, 5595, 5596, 201, 205, 
	5597, 207, 220, 224, 262, 5598, 5599, 5600, 5601, 279, 295, 5602, 298, 299, 301, 5603, 
	312, 5604, 326, 327, 328, 329, 330, 331, 332, 333, 334, 335, 339, 341, 342, 5605, 347, 
	5606, 349, 351, 352, 353, 354, 357, 358, 359, 361, 365, 366, 367, 368, 369, 373, 378, 
	381, 382, 383, 392, 393, 394, 396, 5607, 401, 402, 403, 404, 405, 407, 408, 409, 410, 
	429, 430, 5608, 432, 434, 435, 437, 438, 439, 440, 441, 446, 447, 453, 5609, 456, 457, 
	458, 463, 464, 465, 467, 469, 471, 474, 475, 476, 478, 479, 5398, 5399, 5400, 481, 482, 
	485, 5610, 486, 489, 490, 494, 497, 498, 501, 502, 503, 504, 505, 507, 508, 509, 511, 
	512, 514, 515, 516, 517, 518, 519, 521, 522, 523, 524, 525, 526, 527, 528, 529, 533, 
	537, 541, 542, 543, 548, 549, 559, 560, 566, 571, 572, 573, 574, 575, 576, 5611, 5612, 
	5613, 5614, 5615, 5616, 5617, 5618, 5619, 586, 587, 5418
)));

define('USER_DEPARTMENT', serialize(array(
	0 => 'Academics',
	1 => 'Accounting',
	2 => 'Administrative',
	3 => 'Business development',
	17 => 'Customer Service',
	5 => 'Engineering',
	7 => 'Human resources',
	8 => 'Information technology',
	9 => 'Legal',
	19 => 'Mail Room & Delivery',
	10 => 'Marketing',
	12 => 'Operations',
	14 => 'Public relations',
	16 => 'Sales',
	20 => 'Warehouse',
	18 => 'Other',
)));
define('USER_DEPARTMENT_MAPPING', serialize(array(
	590, 591, 592, 593, 594, 595, 596, 597, 598, 599, 600, 601, 602, 603, 604, 605, 606, 607, 5419
)));

define('USER_MARITAL', serialize(array(
	0 => 'Prefer not to say',
	1 => 'Single',
	2 => 'In a relationship',
	3 => 'Engaged',
	4 => 'Married',
	5 => 'Divorced',
	6 => 'Widowed'
)));
define('USER_MARITAL_MAPPING', serialize(array(
	621, 622, 623, 624, 625, 626, 627
)));

define('USER_ORG_SIZE', serialize(array(
	0 => '1 employee',
	10 => '2-5 employees',
	9 => '6-10 employees',
	8 => '11-50 employees',
	7 => '51-100 employees',
	6 => '101-249 employees',
	5 => '250-500 employees',
	4 => '501-1,000 employees',
	3 => '1,001-5,000 employees',
	2 => '5,001-10,000 employees',
	1 => '10,000+ employees',
	11 => 'Not sure'
)));
define('USER_ORG_SIZE_MAPPING', serialize(array(
	907, 917, 916, 915, 914, 913, 912, 911, 910, 909, 908, 918
)));

define('USER_ORG_REVENUE', serialize(array(
	0 => 'Less than $100,000',
	12 => '$100,000 up to $500,000',
	11 => '$500,000 up to $1 million',
	10 => '$1 million up to $5 million',
	9 => '$5 million up to $10 million',
	8 => '$10 million up to $50 million',
	7 => '$50 million up to $100 million',
	6 => '$100 million up to $500 million',
	5 => '$500 million up to $1 billion',
	4 => '$1 billion up to $5 billion',
	3 => '$5 billion up to $10 billion',
	2 => '$10 billion or more',
	1 => 'Not sure',
	13 => 'Decline to answer',
)));
define('USER_ORG_REVENUE_MAPPING', serialize(array(919, 931, 930, 929, 928, 927, 926, 925, 924, 923, 922, 921, 920, 932)));

define('USER_HOME', serialize(array(
	0 => 'Own',
	1 => 'Rent',
)));
define('USER_HOME_MAPPING', serialize(array(5576, 5575))); 

define('USER_HOME_OWNERSHIP', serialize(array(
	0 => 'No',
	1 => 'Yes',
)));
define('USER_HOME_OWNERSHIP_MAPPING', serialize(array(
	5578, 5577
)));

define('USER_HOME_PLANS', serialize(array(
	0 => 'Buy an existing home (previously owned)',
	1 => 'Buy a newly constructed home',
	2 => 'Spend more than $10,000 on modifications to your home',
	3 => 'None of the above',
)));
define('USER_HOME_PLANS_MAPPING', serialize(array(3166, 3167, 3168, 3169)));

define('USER_SMARTPHONE', serialize(array(1 => 'Yes', 0 => 'No')));
define('USER_SMARTPHONE_MAPPING', serialize(array(3526, 3525))); 

define('USER_TABLET', serialize(array(1 => 'Yes', 0 => 'No')));
define('USER_TABLET_MAPPING', serialize(array(5574, 5573))); 

define('USER_TRAVEL', serialize(array(
	0 => 'Yes - Domestic only',
	1 => 'Yes - International only',
	2 => 'Yes - Domestic and International',
	3 => 'I have not traveled by airline within the past 12 months',
)));
define('USER_TRAVEL_MAPPING', serialize(array(4348, 4349, 4350, 4351))); 
