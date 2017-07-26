<?php

class CoreQuestionShell extends AppShell {
	var $uses = array('Question', 'Answer', 'QuestionText', 'AnswerText');
	
	public $core_questions = array(
		// BIG 3
		'gender' => array(
			'text' => "What is your gender?",
			'answers' => array(
				"Male",
				"Female"
			),
			'type' => "single punch",
			'countries' => array("US", "CA", "GB")
		),
		'postal_code' => array(
			'text' => "Please enter your postal code",
			'type' => "single open ended",
			'countries' => array("US", "CA", "GB")
		),
		'age' => array(
			'text' => "Please enter your date of birth",
			'type' => "single open ended",
			'countries' => array("US", "CA", "GB")
		),
		
		// HHI / EDUCATION
		'hhi' => array(
			'text' => "Please enter your household annual gross income",
			'type' => "single open ended int",
			'countries' => array("US", "CA", "GB")
		),
		'education' => array(
			'text' => "What is the highest level of education you have completed?",
			'answers' => array(
				"None completed",
				"3rd grade or less",
				"Middle school",
				"Completed some high school, but did not graduate",
				"High school graduate",
				"Other post high school vocational training",
				"Completed some college, but did not graduate",
				"Associate's Degree",
				"Bachelor's Degree",
				"Completed some graduate school, but did not graduate",
				"Master's or professional degree",
				"Doctorate Degree"
			),
			'type' => "single punch",
			'countries' => array("US", "CA", "GB")
		),
		
		// CHILDREN
		'children_under_18' => array(
			'text' => "Do you have any children under the age of 18?",
			'answers' => array(
				"Yes",
				"No"
			),
			'type' => "single punch",
			'countries' => array("US", "CA", "GB")
		),
		// If has children ^
		'children_age_and_gender' => array(
			'text' => "Please enter your the birthdate and gender of each child under 18",
			'answers' => array(
				"Male 1 months",
				"Male 2 months",
				"Male 3 months",
				"Male 4 months",
				"Male 5 months",
				"Male 6 months",
				"Male 7 months",
				"Male 8 months",
				"Male 9 months",
				"Male 10 months",
				"Male 11 months",
				"Male 12 months",
				"Male 13 months",
				"Male 14 months",
				"Male 15 months",
				"Male 16 months",
				"Male 17 months",
				"Male 18 months",
				"Male 19 months",
				"Male 20 months",
				"Male 21 months",
				"Male 22 months",
				"Male 23 months",
				"Male 24 months",
				"Male 25 months",
				"Male 26 months",
				"Male 27 months",
				"Male 28 months",
				"Male 29 months",
				"Male 30 months",
				"Male 31 months",
				"Male 32 months",
				"Male 33 months",
				"Male 34 months",
				"Male 35 months",
				"Male 3 years",
				"Male 4 years",
				"Male 5 years",
				"Male 6 years",
				"Male 7 years",
				"Male 8 years",
				"Male 9 years",
				"Male 10 years",
				"Male 11 years",
				"Male 12 years",
				"Male 13 years",
				"Male 14 years",
				"Male 15 years",
				"Male 16 years",
				"Male 17 years",
				"Female 1 months",
				"Female 2 months",
				"Female 3 months",
				"Female 4 months",
				"Female 5 months",
				"Female 6 months",
				"Female 7 months",
				"Female 8 months",
				"Female 9 months",
				"Female 10 months",
				"Female 11 months",
				"Female 12 months",
				"Female 13 months",
				"Female 14 months",
				"Female 15 months",
				"Female 16 months",
				"Female 17 months",
				"Female 18 months",
				"Female 19 months",
				"Female 20 months",
				"Female 21 months",
				"Female 22 months",
				"Female 23 months",
				"Female 24 months",
				"Female 25 months",
				"Female 26 months",
				"Female 27 months",
				"Female 28 months",
				"Female 29 months",
				"Female 30 months",
				"Female 31 months",
				"Female 32 months",
				"Female 33 months",
				"Female 34 months",
				"Female 35 months",
				"Female 3 years",
				"Female 4 years",
				"Female 5 years",
				"Female 6 years",
				"Female 7 years",
				"Female 8 years",
				"Female 9 years",
				"Female 10 years",
				"Female 11 years",
				"Female 12 years",
				"Female 13 years",
				"Female 14 years",
				"Female 15 years",
				"Female 16 years",
				"Female 17 years",
				"No kids"
			),
			'type' => "multi punch",
			'countries' => array("US", "CA", "GB")
		),
		
		// OCCUPATION
		// household industries (as opposed to personal)
		'primary_industry' => array(
			'text' => 'Do you, or does anyone in your household, work in any of the following industries?',
			'answers' => array(
				"Accounting",
				"Advertising",
				"Agriculture/Fishing",
				"Architecture",
				"Automotive",
				"Aviation",
				"Banking/Financial",
				"Bio-Tech",
				"Brokerage",
				"Carpenting/Electrical installations/VVS",
				"Chemicals/Plastics/Rubber",
				"Communications/Information",
				"Computer Hardware",
				"Computer Reseller (software/hardware)",
				"Computer Software, Storage, Security",
				"Construction",
				"Consulting",
				"Consumer Electronics",
				"Consumer Packaged Goods",
				"Education",
				"Energy/Utilities/Oil and Gas",
				"Engineering",
				"Environmental Services",
				"Fashion/Apparel",
				"Food/Beverage",
				"Government/Public Sector",
				"Healthcare",
				"Hospitality/Tourism",
				"Human Resources",
				"Information Technology/IT",
				"Insurance",
				"Internet/Web Development",
				"Legal/Law",
				"Manufacturing",
				"Market Research",
				"Marketing/Sales",
				"Media/Entertainment",
				"Military",
				"Non Profit/Social services",
				"Personal Services",
				"Pharmaceuticals",
				"Publishing/Printing/Print Media",
				"Public Relations",
				"Real Estate/Property",
				"Retail/Wholesale trade",
				"Security",
				"Shipping/Distribution",
				"Telecommunications",
				"Transportation",
				"Other",
				"News Media",
				"Journalism",
				"Press Industry"
			),
			'type' => 'multi punch',
			'countries' => array("US", "CA", "GB")
		),
		'employment' => array(
			'text' => "What best describes your current employment status?",
			'answers' => array(
				"Employed full-time",
				"Employed part-time",
				"Self-employed; Business owner full-time",
				"Self-employed; Business owner part-time",
				"Active military",
				"Inactive military/Veteran",
				"Unemployed",
				"Homemaker",
				"Retired",
				"Student",
				"Disabled",
				"Parental leave",
				"Leave of absence"
			),
			'type' => "single punch",
			'countries' => array("US", "CA", "GB")
		),
		// If has job ^
		'org_size' => array(
			'text' => "Approximately how many employees work at your organization?",
			'answers' => array(
				"1",
				"2-5",
				"6-10",
				"11-25",
				"26-50",
				"51-100",
				"101-250",
				"251-500",
				"501-1000",
				"1001-5000",
				"Greater than 5000",
				"I don't work/I don't know"
			),
			'type' => "single punch",
			'countries' => array("US", "CA", "GB")
		),
		'org_primary_industry' => array(
			'text' => "Which of the following categories best describes your organization's primary industry?",
			'answers' => array(
				"Accounting",
				"Advertising",
				"Agriculture/Fishing",
				"Architecture",
				"Automotive",
				"Aviation",
				"Banking/Financial",
				"Bio-Tech",
				"Brokerage",
				"Carpentry/Electrical installations/Plumbing",
				"Chemicals/Plastics/Rubber",
				"Communications/Information",
				"Computer Hardware",
				"Computer Reseller (software/hardware)",
				"Computer Software",
				"Construction",
				"Consulting",
				"Consumer Electronics",
				"Consumer Packaged Goods",
				"Education",
				"Energy/Utilities/Oil and Gas",
				"Engineering",
				"Environmental Services",
				"Fashion/Apparel",
				"Food/Beverage",
				"Government/Public Sector",
				"Healthcare",
				"Hospitality/Tourism",
				"Human Resources",
				"I don't work",
				"Information Technology/IT",
				"Insurance",
				"Internet",
				"Legal/Law",
				"Manufacturing",
				"Market Research",
				"Marketing/Sales",
				"Media/Entertainment",
				"Military",
				"Non Profit/Social services",
				"Other",
				"Personal Services",
				"Pharmaceuticals",
				"Printing Publishing",
				"Public Relations",
				"Real Estate/Property",
				"Retail/Wholesale trade",
				"Security",
				"Shipping/Distribution",
				"Telecommunications",
				"Transportation"
			),
			'type' => 'single punch',
			'countries' => array("US", "CA", "GB")
		),
		'company_revenue' => array(
			'text' => 'Approximately what is the annual revenue for your organization?',
			'answers' => array(
				"Under $100,000",
				"$100,000 - $249,999",
				"$250,000 - $499,999",
				"$500,000 - $999,999",
				"$1 Million - $4.99 Million",
				"$5 Million - $9.99 Million",
				"$10 Million - $24.99 Million",
				"$25 Million - $49.99  Million",
				"$50 Million - $99.99 Million",
				"$100 Million - $249.99 Million",
				"$250 Million - $499.99 Million",
				"$500 Million - $999.99 Million",
				"$1 Billion or more"
			),
			'type' => 'single punch',
			'countries' => array("US", "CA", "GB")
		),
		'department' => array(
			'text' => 'Which department do you primarily work within at your organization?',
			'answers' => array(
				"Administration/General Staff",
				"Customer Service/Client Service",
				"Executive Leadership",
				"Finance/Accounting ",
				"Human Resources",
				"Legal/Law",
				"Marketing",
				"Operations",
				"Procurement",
				"Sales/Business Development",
				"Technology Development Hardware (not only IT)",
				"Technology Development Software (not only IT)",
				"Technology Implementation ",
				"Electrical contractor",
				"Other"
			),
			'type' => 'single punch',
			'countries' => array("US", "CA", "GB")
		),
		'department_purchasing' => array(
			'text' => 'Which departments/products do you have influence or decision making authority on spending/purchasing?',
			'answers' => array(
				"IT Hardware",
				"IT Software",
				"Printers and Copiers",
				"Financial Department",
				"Human Resources",
				"Office supplies",
				"Printer and Copier Supplies",
				"Corporate Travel (Self)",
				"Corporate Travel (Company)",
				"Telecommunications (Mobile)",
				"Telecommunications (Non -Mobile)",
				"Sales/Business Development",
				"Shipping/Mail Services",
				"Operations/Production",
				"Legal Services",
				"Other Professional Services/Consultants",
				"Marketing/Advertising",
				"Security",
				"Facilities Maintenance and Management",
				"Food Services/Catering",
				"Auto Leasing / Purchasing",
				"Utilities",
				"I don’t have influence or decision making authority"
			),
			'type' => 'multi punch',
			'countries' => array("US", "CA", "GB")
		),
		'title' => array(
			'text' => 'What is your job title, level, or responsibility?',
			'answers' => array(
				"C-Level (e.g. CEO, CFO), Owner, Partner, President",
				"Vice President (EVP, SVP, AVP, VP)",
				"Director (Group Director, Sr. Director, Director)",
				"Manager (Group Manager, Sr. Manager, Manager, Program Manager)",
				"Analyst",
				"Assistant or Associate",
				"Administrative (Clerical or Support Staff)",
				"Consultant",
				"Intern",
				"Volunteer",
				"None of the above"
			),
			'type' => 'single punch',
			'countries' => array("US", "CA", "GB")
		),
		
		// Country specific - US
		'ethnicity_us' => array(
			'text' => "What best describes your ethnicity?",
			'answers' => array(
				"White",
				"Black",
				"Hispanic / Latino",
				"Asian - Indian",
				"Asian - Chinese",
				"Asian - Filipino",
				"Asian - Japanese",
				"Asian - Korean",
				"Asian - Vietnamese",
				"Asian - Other",
				"Pacific Islander - Native Hawaiian",
				"Pacific Islander - Guamanian",
				"Pacific Islander - Samoan",
				"Pacific Islander - Other",
				"Middle Eastern",
				"Caribbean",
				"Native American",
				"Other"
			),
			'type' => "single punch",
			'countries' => array("US")
		),
		// If hispanic is selected ^
		'hispanic' => array(
			'text' => "What best describes your Hispanic / Latino heritage?",
			'answers' => array(
				"Mexican",
				"Cuban",
				"Argentinian",
				"Colombian",
				"Ecuadorian",
				"Salvadorian",
				"Guatemalan",
				"Nicaraguan",
				"Panamanian",
				"Peruvian",
				"Spanish",
				"Venezuelan",
				"Other"
			),
			'type' => "single punch",
			'countries' => array("US")
		),
		// Country specific - CA
		'ethnicity_ca' => array(
			'text' => "What best describes your ethnicity?",
			'answers' => array(
				"Asian (including Chinese, Japanese, Korean, etc.)",
				"Black / African Canadian / Caribbean",
				"British / Scottish / Irish / Welsh",
				"Eastern European, including Russia",
				"Hispanic / Latin American",
				"Middle Eastern",
				"South Asian (including India, Pakistan, Sri Lanka, Bangladesh, Nepal)",
				"South East Asian (including Burma, Thailand, Vietnam, Laos, Cambodia, Philippines, Singapore, etc.)",
				"Western European",
				"First Nations / Métis / Innuit",
				"Native Hawaiian or Pacific Islander",
				"Other"
			),
			'type' => "single punch",
			'countries' => array("CA")
		),
		// Country specific - GB
		// note that this one isn't really country specific, but is used frequently
		// in GB surveys
		'smoke' =>  array(
			'text' => "Do you smoke?",
			'answers' => array(
				"Yes, I smoke",
				"Yes, I smoke now and then",
				"Yes, I smoke but I'm planning to quit",
				"No, I don't smoke",
				"No, I have quit",
				"No, I don't smoke, but use other tobacco products"
			),
			'type' => "single punch",
			'countries' => array("GB")
		)
	);
	
	// Import questions / answers to DB
	function import() {
		foreach ($this->core_questions as $question_id => $info) {
			$questionDataSource = $this->Question->getDataSource();
			$questionDataSource->begin();
			
			$this->Question->create();
			$saved = $this->Question->save(array('Question' => array(
				'partner' => 'core',
				'partner_question_id' => $question_id,
				'question' => $question_id,
				'question_type' => $info['type'],
				'core' => false,
				'ignore' => true,
				'staging' => true,
				'deprecated' => false,
				'locked' => false,
				'public' => false
			)), true, array('partner', 'partner_question_id', 'question', 'question_type', 'core', 'ignore', 'staging', 'deprecated', 'locked', 'public'));
			
			if ($saved) {
				$id = $this->Question->getLastInsertId();
				$mintvine_question = $this->Question->findById($id);
				$questionDataSource->commit();
				
				// question texts
				foreach($info['countries'] as $country) {
					$this->QuestionText->create();
					$this->QuestionText->save(array('QuestionText' => array(
						'question_id' => $id,
						'country' => $country,
						'text' => $info['text'],
					)), true, array('question_id', 'country', 'text'));
				}
				
				// answers
				if (isset($info['answers'])) {
					foreach ($info['answers'] as $answer_id => $answer) {
						$answerDataSource = $this->Answer->getDataSource();
						$answerDataSource->begin();
						
						$this->Answer->create();
						$answer_saved = $this->Answer->save(array('Answer' => array(
							'question_id' => $id,
							'ignore' => true,
							'partner_answer_id' => $answer_id
						)), true, array('question_id', 'ignore', 'partner_answer_id'));
						
						if ($answer_saved) {
							$a_id = $this->Answer->getLastInsertId();
							$mintvine_answer = $this->Answer->findById($a_id);
							$answerDataSource->commit();
							
							// answer texts
							foreach($info['countries'] as $country) {
								$this->AnswerText->create();
								$this->AnswerText->save(array('AnswerText' => array(
									'answer_id' => $a_id,
									'country' => $country,
									'text' => $answer
								)), true, array('answer_id', 'country', 'text'));
							}
						}
						else {
							$answerDataSource->commit();
						}
					}
				}
			}
			else {
				$questionDataSource->commit();
			}
		}
	}
	
	public function delete_question() {
		if (!isset($this->args[0])) {
			$this->out('Please input a partner question id'); 
			return false;
		}
		
		$partner_question_id = $this->args[0];
		$partner = "core";
	
		$question = $this->Question->find('first', array(
			'conditions' => array(
				'Question.partner' => $partner,
				'Question.partner_question_id' => $partner_question_id
			),
			'fields' => array('Question.id')
		));
		
		$answers = $this->Answer->find('all', array(
			'conditions' => array(
				'Answer.question_id' => $question['Question']['id']
			),
			'fields' => array('Answer.id')
		));
		
		foreach ($answers as $answer) {
			$answer_texts = $this->AnswerText->find('all', array(
				'conditions' => array(
					'AnswerText.answer_id' => $answer['Answer']['id']
				),
				'fields' => array('AnswerText.id')
			));
			
			foreach ($answer_texts as $answer_text) {
				$this->AnswerText->delete($answer_text['AnswerText']['id']);
			}
			
			$this->Answer->delete($answer['Answer']['id']);
		}
		
		$question_texts = $this->QuestionText->find('all', array(
			'conditions' => array(
				'QuestionText.question_id' => $question['Question']['id']
			),
			'fields' => array('QuestionText.id')
		));
		
		foreach ($question_texts as $question_text) {
			$this->QuestionText->delete($question_text['QuestionText']['id']);
		}
		
		$this->Question->delete($question['Question']['id']);
	}
}