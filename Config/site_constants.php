<?php

require_once('site_settings.php');

define('DB_DATE', 'Y-m-d');
define('DB_DATETIME', 'Y-m-d H:i:s');
define('DB_ACTIVE', 'active');
define('DB_DEACTIVE', 'deactive');
define('DB_PENDING', 'pending');

define('USER_GENDER_MALE', '1');
define('USER_GENDER_FEMALE', '2'); 

define('USER_GENDERS', serialize(array(
	USER_GENDER_MALE => 'Male',
	USER_GENDER_FEMALE => 'Female'
)));

define('ADMIN_ROLES', serialize(array('tech' => 'Tech', 'pm' => 'Project Manager', 'am' => 'Account Manager')));

define('SUPPORTED_COUNTRIES', serialize(array('US' => 230, 'CA' => 38, 'GB' => 76))); // us, canada, uk
 
define('POLL_STREAK_COUNT', 10);

# imported from old site
define('TRANSACTION_OFFER', '1'); // revenue gen
define('TRANSACTION_SURVEY', '2'); // revenue gen
define('TRANSACTION_REFERRAL', '3');
define('TRANSACTION_WITHDRAWAL', '4');
define('TRANSACTION_POLL', '5');
define('TRANSACTION_EMAIL', '6');
define('TRANSACTION_PROFILE', '7');
define('TRANSACTION_OTHER', '8');
define('TRANSACTION_MINTROLL_SURVEY', '9'); // revenue gen - DONE
define('TRANSACTION_GROUPON', '10'); // revenue generating
define('TRANSACTION_DWOLLA', '11');
define('TRANSACTION_CODE', '12');
define('TRANSACTION_GOOGLE', '13');
define('TRANSACTION_PENDING_DAYS', '14');
define('TRANSACTION_SURVEY_NQ', '15');
define('TRANSACTION_POLL_STREAK', '16');
define('TRANSACTION_CHALLENGE', '17');
define('TRANSACTION_QUALIFICATION_PAYOUT', '18');
define('TRANSACTION_ACCOUNT', '19');
define('TRANSACTION_MANUAL_TANGOCARD', '20');
define('TRANSACTION_MISSING_POINTS', '21');
define('TRANSACTION_MANUAL_PAYPAL', '22');
define('TRANSACTION_MANUAL_DWOLLA', '23');

define('TRANSACTION_TYPES', serialize(array(
	TRANSACTION_OFFER => 'Offer',
	TRANSACTION_GROUPON => 'Local Deals',
	TRANSACTION_SURVEY => 'Survey',
	TRANSACTION_SURVEY_NQ => 'Survey NQ',
	TRANSACTION_GOOGLE => 'Google Survey',
	TRANSACTION_REFERRAL => 'Referral',
	TRANSACTION_WITHDRAWAL => 'Withdrawal',
	TRANSACTION_POLL => 'Poll',
	TRANSACTION_POLL_STREAK => 'Poll streak',
	TRANSACTION_EMAIL => 'Email confirmation',
	TRANSACTION_PROFILE => 'Profile',
	TRANSACTION_DWOLLA => 'Dwolla Bonus',
	TRANSACTION_CODE => 'Promotional Code',
	TRANSACTION_CHALLENGE => 'Complete Challenge',
	TRANSACTION_OTHER => 'Other',
	TRANSACTION_QUALIFICATION_PAYOUT => 'Qualification Payout',
	TRANSACTION_ACCOUNT => 'Account Profile Payouts',
	TRANSACTION_MANUAL_TANGOCARD => 'Manual Tangocard Send',
	TRANSACTION_MISSING_POINTS => 'Missing Points',
	TRANSACTION_MANUAL_PAYPAL => 'Manual Paypal Send',
	TRANSACTION_MANUAL_DWOLLA => 'Manual Dwolla Send'
)));

define('TRANSACTION_APPROVED', 'Approved');
define('TRANSACTION_PENDING', 'Pending');
define('TRANSACTION_NA', 'N/A');
define('TRANSACTION_REJECTED', 'Rejected');

define('TRANSACTION_STATUSES', serialize(array(
	TRANSACTION_APPROVED => 'Approved',
	TRANSACTION_PENDING => 'Pending',
	TRANSACTION_NA => 'N/A',
	TRANSACTION_REJECTED => 'Rejected'
))); 

define('PAYMENT_PAYPAL', 'paypal');
define('PAYMENT_DWOLLA', 'dwolla');
define('PAYMENT_TANGO', 'tango');
define('PAYMENT_MVPAY', 'mvpay');

define('PAYMENT_METHODS', serialize(array(
	PAYMENT_PAYPAL => 'Paypal',
	PAYMENT_DWOLLA => 'Dwolla',
	PAYMENT_TANGO => 'Tango',
	PAYMENT_MVPAY => 'MVPay'
)));

define('SURVEY_CLICK', '1');
define('SURVEY_COMPLETED', '2');
define('SURVEY_NQ', '3');
define('SURVEY_OVERQUOTA', '4');
define('SURVEY_DUPE', '5');
define('SURVEY_INTERNAL_NQ', '6');
define('SURVEY_DUPE_FP', '7');
define('SURVEY_NQ_FRAUD', '8');
define('SURVEY_NQ_SPEED', '9');
define('SURVEY_NQ_EXCLUDED', '10');
define('SURVEY_OQ_INTERNAL', '11');
define('SURVEY_CUSTOM', '12');

define('SURVEY_STATUSES', serialize(array(
	SURVEY_CLICK => 'Click',
	SURVEY_COMPLETED => 'Complete', 
	SURVEY_NQ => 'NQ',
	SURVEY_OVERQUOTA => 'OQ',
	SURVEY_OQ_INTERNAL => 'OQ (Internal)',
	SURVEY_DUPE => 'Dupe',
	SURVEY_INTERNAL_NQ => 'NQ (Internal)',
	SURVEY_DUPE_FP => 'Fingerprint Dupe',
	SURVEY_NQ_FRAUD => 'NQ (Fraud)',
	SURVEY_NQ_SPEED => 'NQ (Speeding)',
	SURVEY_NQ_EXCLUDED => 'NQ (Excluded)',
	SURVEY_CUSTOM => 'Custom'
))); 

define('SURVEY_TERMINATING_ACTIONS', serialize(array(
	SURVEY_COMPLETED, 
	SURVEY_NQ, 
	SURVEY_DUPE,
	SURVEY_INTERNAL_NQ, 
	SURVEY_DUPE_FP, 
	SURVEY_NQ_FRAUD, 
	SURVEY_NQ_SPEED,
	SURVEY_OVERQUOTA, 
)));

// this notes the payout statuses in transactions
define('PAYOUT_SUCCEEDED', '1');
define('PAYOUT_FAILED', '-1');
define('PAYOUT_UNPROCESSED', '0');

// do not change these
define('PROJECT_STATUS_STAGING', 'Staging');
define('PROJECT_STATUS_SAMPLING', 'Sampling');
define('PROJECT_STATUS_OPEN', 'Open');
define('PROJECT_STATUS_CLOSED', 'Closed');
define('PROJECT_STATUS_INVOICED', 'Closed & Invoiced');

define('PROJECT_STATUSES', serialize(array(
	PROJECT_STATUS_STAGING => 'Staging',
	PROJECT_STATUS_SAMPLING => 'Sampling',
	PROJECT_STATUS_OPEN => 'Open',
	PROJECT_STATUS_CLOSED => 'Closed',
	PROJECT_STATUS_INVOICED => 'Invoiced'
))); 

define('TYPE_OFFER', '1');
define('TYPE_PROFILE', '2');
define('TYPE_SURVEY', '3');
define('TYPE_POLL', '4');

define('LOG_TYPE_LOGIN', 'login');
define('LOG_TYPE_REGISTRATION', 'registration');
define('LOG_TYPE_SURVEY', 'survey');
define('LOG_TYPE_WITHDRAWAL', 'withdrawal');

/* SCORING ALGORITHM
	`countries 				has the user accessed the site from outside the country?
	`referral` 				was the user referred by a hellbanned user?
	`language` 				does the user's accepted languages on a browser match english?
	`locations` 			does the user log-in through multiple locations?
	`asian_timezone` 		does the user have a vastly differently timezone than the self-reported US one?
	`proxy` 				does the user utilize servers with a high proxy score?
	`distance` 				does the user utilize IP addresses that are geographically diverse? 
	`timezone` 				does the user utilize a timezone that doesn't match self-reported IP? 
	`minfraud` 				what is the minfraud score of this user?
	`profile` 				has the user completed profiles in a good period of time?		
	`frequent` 				registered or issued withdrawal within 7 days?	
	`mobile_verified` 		User has verified their phone number.
	`duplicate_number` 		User has a duplicate phone number with other accounts.
*/
define('USER_ANALYSIS_WEIGHTS', serialize(array(
	'countries' => 30,
	'referral' => 15,
	'language' => 20,
	'locations' => 10,
	'asian_timezone' => 30,
	'logins' => 10,
	'proxy' => 20,
	'distance' => 10, 
	'frequency' => 40, 
	'timezone' => 20,
	'minfraud' => 10,
	/* 'profile' => 30, */
	'rejected_transactions' => 30,
	'poll_revenue' => 30,
	'offerwall' => 30,
	'mobile_verified' => 25,
	'payout' => 50,
	/* 'nonrevenue' => 20 */
	'duplicate_number' => 50,
	'ip_address' => 30
)));

define('USER_SOURCES', serialize(array(
	'coreg' => 'Permission Data',
	'panthera' => 'Panthera (US)',
	'panthera2' => 'Panthera (US Incent)',
	'panthera3' => 'Panthera (UK)',
	'panthera4' => 'Panthera (UK Incent)',
	'panthera5' => 'Panthera (US - NEW)',
	'panthera6' => 'Panthera (US Incent - NEW)',
	'rocketroi' => 'RocketROI',
	'competeroi' => 'ROI - MR Incent',
	'competeroi2' => 'ROI - MR Nonincent',
	'fborganic' => 'Facebook Organic (Posts)',
	'facebook' => 'Facebook Landers',
	'mvf-lander' => 'MVF (Lander)',
	'mvf-coreg' => 'MVF (Coreg)',
	'ad' => 'Audience Delivered (External)',
	'fbint' => 'Science - Default - FB Tab',
	'fbext' => 'Science - Default - FB External',
	'sc-couch-fb-tab' => 'Science - Couch - FB Tab',
	'sc-couch-fb-ext' => 'Science - Couch - FB External',
	'sc-couple-fb-tab' => 'Science - Couple - FB Tab',
	'sc-couple-fb-ext' => 'Science - Couple - FB External',
	'sc-couple-red-fb-tab' => 'Science - Couple (Red) - FB Tab',
	'sc-couple-red-fb-ext' => 'Science - Couple (Red) - FB External',
	'sc-couple-ill-fb-tab' => 'Science - Couple (Illustrated) - FB Tab',
	'sc-couple-ill-fb-ext' => 'Science - Couple (Illustrated) - FB External',
	'mvm-pt' => 'MVM - Panthera',
	'mvm-ad' => 'MVM - Audience Delivered',
	'mvm-science' => 'MVM - Science',
)));

define('OFFER_THINKACTION', 'thinkaction');
define('OFFER_W4', 'w4');
define('OFFER_OFFERTORO', 'offertoro');
define('OFFER_PERSONALY', 'personaly');
define('OFFER_PEANUTLABS', 'peanutlabs');
define('OFFER_TRIALPAY', 'trialpay');
define('OFFER_ADWALL', 'adwall');
define('OFFER_SUPERREWARDS', 'superrewards');
define('OFFER_ADGATE', 'adgate');

define('OFFER_PARTNERS', serialize(array(
	OFFER_THINKACTION => 'ThinkAction',
	OFFER_W4 => 'W4',
	OFFER_OFFERTORO => 'Offertoro',
	OFFER_PERSONALY => 'Personaly',
	OFFER_PEANUTLABS => 'PeanutLabs',
	OFFER_TRIALPAY => 'TrialPay',
	OFFER_ADWALL => 'AdWall',
	OFFER_SUPERREWARDS => 'SuperRewards',
	OFFER_ADGATE => 'AdGate',
)));

define('REDEMPTION_REJECTED', '-1');
define('REDEMPTION_OPENED', '0');
#define('REDEMPTION_TAKEN', '1'); //not really possible to track this state
define('REDEMPTION_ACCEPTED', '2');
define('REDEMPTION_REDEEMED', '3');
define('REDEMPTION_STATUSES', serialize(array(
	REDEMPTION_REJECTED => 'Rejected', 
	REDEMPTION_OPENED => 'Opened', 
	REDEMPTION_ACCEPTED => 'Accepted',
	REDEMPTION_REDEEMED => 'Redeemed'
)));

//Offer Redemption statuses
define('OFFER_REDEMPTION_REJECTED', 'rejected');
define('OFFER_REDEMPTION_ACCEPTED', 'accepted');

define('CONTACT_TYPES', serialize(array(
	'Client' => 'Client', 
	'Partner' => 'Partner', 
	'Custom' => 'Custom'
)));
define('IM_TYPES', serialize(array(
	'Skype' => 'Skype', 
	'MSN' => 'MSN',
	'AIM' => 'AIM'
)));
define('SURVEY_TYPES', serialize(array(
	'1' => 'Profile', 
	'4' => 'External'
)));
define('CURRENCY_USD', 1);
define('CURRENCY_GBP', 2);
define('CURRENCY_EURO', 3);

define('CURRENCY', serialize(array(
	CURRENCY_USD => '$',
	CURRENCY_GBP => '£',
	CURRENCY_EURO => '€'
)));

define('SURVEY_HIDDEN_NO_REASON', 1);
define('SURVEY_HIDDEN_TOO_LONG', 2);
define('SURVEY_HIDDEN_TOO_SMALL', 3);
define('SURVEY_HIDDEN_NOT_WORKING', 4);
define('SURVEY_HIDDEN_JUST_DO_NOT_WANT', 5);
define('SURVEY_HIDDEN_OTHER', 6);
define('SURVEY_HIDDEN_SAMPLING', 7);

define('SURVEY_HIDDEN', serialize(array(
	SURVEY_HIDDEN_NO_REASON => 'No reason',
	SURVEY_HIDDEN_TOO_LONG => 'Too long',
	SURVEY_HIDDEN_TOO_SMALL => 'Too small of a payout',
	SURVEY_HIDDEN_NOT_WORKING => "Tried taking it, but it didn't work",
	SURVEY_HIDDEN_JUST_DO_NOT_WANT => "Just don't want to",
	SURVEY_HIDDEN_OTHER => 'Other',
)));

# Spectrum Surveys import status
define('SPECTRUM_SURVEY_CREATED', 'imported');
define('SPECTRUM_SURVEY_QUALIFICATIONS_LOADED', 'qualifications.ready');
define('SPECTRUM_SURVEY_QUALIFICATIONS_EMPTY', 'qualifications.none');

# Fed Surveys import status
define('FEDSURVEY_CREATED', 'imported');
define('FEDSURVEY_QUALIFICATIONS_LOADED', 'qualifications.ready');
define('FEDSURVEY_QUALIFICATIONS_EMPTY', 'qualifications.none');

define('FED_MAGIC_NUMBER', 159999); 

define('PARAM_TYPES', serialize(array(
	'points2shop' => 'points2shop'
))); 

define('PAYMENT_LOG_STARTED', '1');
define('PAYMENT_LOG_ABORTED', '2');
define('PAYMENT_LOG_FAILED', '3');
define('PAYMENT_LOG_SUCCESSFUL', '4');

define('QUICKBOOK_OAUTH_NOT_CONNECTED', 1);
define('QUICKBOOK_OAUTH_CONNECTED', 2);
define('QUICKBOOK_OAUTH_EXPIRING_SOON', 3);
define('QUICKBOOK_OAUTH_EXPIRED', 4);

define('PINGBACK_RESULT_COMPLETE', '1');
define('PINGBACK_RESULT_TERMINATE', '2');
define('PINGBACK_RESULT_QUOTA', '3');
define('PINGBACK_RESULT_OTHER', '4');
define('PINGBACK_RESULT_CLOSED', '5');
define('PINGBACK_RESULT_SECURITY', '6');
define('PINGBACK_RESULT_EARLY_TERMINATE', '7');
define('PINGBACK_RESULT_DUPLICATE', '8');
define('PINGBACK_RESULT_EARLY_QUOTA', '9');

define('PINGBACK_RESULT_STATUSES', serialize(array(
	PINGBACK_RESULT_COMPLETE => 'Complete',			// 1 - Complete (redirected to survey and got a complete)
	PINGBACK_RESULT_TERMINATE => 'Terminate',		// 2 - Terminate (redirected to survey, but termed)
	PINGBACK_RESULT_QUOTA => 'Quota',			// 3 - Quota (redirected to survey, but termed because of quotas)
	PINGBACK_RESULT_OTHER => 'Other',			// 4 - Other (redirected to survey, but termed for another cause)
	PINGBACK_RESULT_CLOSED => 'Closed/Paused',		// 5 - Closed/Paused (not redirected to survey because the survey is closed)
	PINGBACK_RESULT_SECURITY => 'Security',			// 6 - Security (not redirected to survey because of security concerns)
	PINGBACK_RESULT_EARLY_TERMINATE => 'Early terminate',	// 7 - Early terminate (not redirected to survey because prescreening was not successful)
	PINGBACK_RESULT_DUPLICATE => 'Duplicate',		// 8 - Duplicate (not redirected to survey because of dedup criteria)
	PINGBACK_RESULT_EARLY_QUOTA => 'Early quota'		// 9 - Early quota (not redirected to survey because of prescreener quota validation)
))); 

define('LIVEP_SCREENED', '1');
define('LIVEP_REDIRECTED', '2');
define('LIVEP_SCREENED_REDIRECTED', '3');
define('LIVEP_DEDUPLICATION', '4');
define('LIVEP_SCREENED_DEDUPLICATION', '5');
define('LIVEP_SECURITY', '8');
define('LIVEP_SCREENED_SECURITY', '9');

define('LIVEP_STATUSES', serialize(array(
	LIVEP_SCREENED => 'Screened',			// 1 – Screened. We asked some information to the respondent because we did not receive and we did not have all the information required to decide if he qualified for the survey.
	LIVEP_REDIRECTED => 'Redirected',		// 2 – Redirected. The respondent was redirected to the survey.
	LIVEP_SCREENED_REDIRECTED => 'Screened and Redirected',
	LIVEP_DEDUPLICATION => 'Deduplication',		// 4 – Deduplication. The respondent was rejected because of deduplication.
	LIVEP_SCREENED_DEDUPLICATION => 'Screened and Deduplication',
	LIVEP_SECURITY => 'Security',			// 8 – Security. The respondent was rejected because of security issues
	LIVEP_SCREENED_SECURITY => 'Screened and Security',
))); 

define('LIVES_BLOCKED', '1');
define('LIVES_SUSPICIOUS', '2');
define('LIVES_INVALID_IP', '3');
define('LIVES_GEOLOCATION_MISMATCH', '4');

define('LIVES_STATUSES', serialize(array(
	LIVES_BLOCKED => 'Blocked',				// 1 - Blocked panelist.
	LIVES_SUSPICIOUS => 'Suspicious IP',			// 2 - Suspicious IP (known proxy)
	LIVES_INVALID_IP => 'Invalid IP',			// 3 - Invalid IP.
	LIVES_GEOLOCATION_MISMATCH => 'Geolocation Mismatch',	// 4 - IP geolocation does not match the country we re ceived.
))); 

define('LIVEI_BIRTHDAY', '1');
define('LIVEI_GENDER', '2');
define('LIVEI_COUNTRY', '3');
define('LIVEI_POSTAL_CODE', '4');

define('LIVEI_STATUSES', serialize(array(
	LIVEI_BIRTHDAY => 'Birthday',		// 1 - Invalid birthday.
	LIVEI_GENDER => 'Gender',		// 2 - Invalid gender.
	LIVEI_COUNTRY => 'Country',		// 3 - Invalid country.
	LIVEI_POSTAL_CODE => 'Postal code',	// 4 - Invalid postal code.
))); 

define('PROJECT_AUTO_STATUS_TEST', 'test');
define('PROJECT_AUTO_STATUS_SEND', 'send');
define('PROJECT_AUTO_STATUS_FAILED', 'failed');

define('PROJECT_AUTO_STATUSES', serialize(array(
	PROJECT_AUTO_STATUS_TEST => 'Test',
	PROJECT_AUTO_STATUS_SEND => 'Send',
	PROJECT_AUTO_STATUS_FAILED => 'Failed',
)));

define('STACKIFY_APP_NAME', 'MV_WEB');
define('STACKIFY_ENVIRONMENT_NAME', 'MV_WEB');

define('TOLUNA_RESPONDENT_API_URL', 'http://ip.surveyrouter.com/IntegratedPanelService/api/Respondent');

define('EMAIL_TYPE_RESEND', 'resend');

define('USER_LEVEL_RUNNER', '1');
define('USER_LEVEL_WALKER', '2');
define('USER_LEVEL_LIVING', '3');
define('USER_LEVEL_ZOMBIE', '4');
define('USER_LEVEL_DEAD', '5');

define('USER_LEVELS', serialize(array(
	USER_LEVEL_RUNNER => 'Runner',
	USER_LEVEL_WALKER => 'Walker',
	USER_LEVEL_LIVING => 'Living',
	USER_LEVEL_ZOMBIE => 'Zombie',
	USER_LEVEL_DEAD => 'Dead',
)));

define('RECONCILE_POINTS2SHOP', 'points2shop');
define('RECONCILE_PERSONALY', 'personaly');
define('RECONCILE_ADWALL', 'adwall');
define('RECONCILE_OFFERTORO', 'offertoro');
define('RECONCILE_SSI', 'ssi');
//define('RECONCILE_TRIALPAY', 'trialpay');
define('RECONCILE_PEANUTLABS', 'peanutlabs');
define('RECONCILE_ADGATE', 'adgate');
define('RECONCILE_LUCID', 'lucid');
define('RECONCILE_TOLUNA', 'toluna');
define('RECONCILE_PRECISION', 'precision');
define('RECONCILE_CINT', 'cint');
define('RECONCILE_SPECTRUM', 'spectrum');
define('RECONCILE_TYPES', serialize(array(
	RECONCILE_POINTS2SHOP => 'Points2Shop',
	RECONCILE_PERSONALY => 'Personaly',
	RECONCILE_ADWALL => 'Adwall',
	RECONCILE_OFFERTORO => 'OfferToro',
	RECONCILE_SSI => 'SSI',
	//RECONCILE_TRIALPAY => 'Trialpay',
	RECONCILE_PEANUTLABS => 'Peanutlabs',
	RECONCILE_ADGATE => 'Adgate',
	RECONCILE_LUCID => 'Lucid',
	RECONCILE_TOLUNA => 'Toluna',
	RECONCILE_PRECISION => 'Precision',
	RECONCILE_CINT => 'Cint',
	RECONCILE_SPECTRUM => 'PureSpectrum',
)));
define('RECONCILE_PROJECTS', serialize(array(
	RECONCILE_POINTS2SHOP,
	RECONCILE_SSI,
	RECONCILE_LUCID,
	RECONCILE_TOLUNA,
	RECONCILE_PRECISION,
	RECONCILE_CINT,
	RECONCILE_SPECTRUM
)));

define('RECONCILIATION_IMPORTED', 'imported');
define('RECONCILIATION_ERROR', 'error');
define('RECONCILIATION_ANALYZED', 'analyzed');
define('RECONCILIATION_COMPLETED', 'completed');
define('RECONCILIATION_STATUSES', serialize(array(
	RECONCILIATION_IMPORTED => 'Imported',
	RECONCILIATION_ERROR => 'Error',
	RECONCILIATION_ANALYZED => 'Analyzed',
	RECONCILIATION_COMPLETED => 'Completed'
)));

define('RECONCILIATION_ANALYSIS_MISSING_COMPLETE', 'missing_complete');
define('RECONCILIATION_ANALYSIS_REJECTED_COMPLETE', 'rejected_complete');
define('RECONCILIATION_ANALYSIS_TYPES', serialize(array(
	RECONCILIATION_ANALYSIS_MISSING_COMPLETE => 'Missing complete',
	RECONCILIATION_ANALYSIS_REJECTED_COMPLETE => 'Rejected complete'
)));

// Add some missing constants
if (!defined('CURL_SSLVERSION_DEFAULT')) { 
	define('CURL_SSLVERSION_DEFAULT', 0);
}
if (!defined('CURL_SSLVERSION_TLSv1')) { 
	define('CURL_SSLVERSION_TLSv1', 1);
}
if (!defined('CURL_SSLVERSION_SSLv2')) {
	define('CURL_SSLVERSION_SSLv2', 2);
}
if (!defined('CURL_SSLVERSION_SSLv3')) {
	define('CURL_SSLVERSION_SSLv3', 3);
}

define('KEEN_INTERVALS', serialize(array(
	'this_1_weeks' => 'weekly', 
	'previous_1_weeks' => 'weekly', 
	'previous_2_weeks' => 'weekly',
	'previous_3_weeks' => 'weekly',
	'this_1_months' => 'monthly',
	'previous_1_months' => 'monthly',
	'previous_30_days' => null,
	'previous_60_days' => null,
	'previous_90_days' => null,
	'this_1_years' => 'monthly',
	'previous_1_years' => 'monthly'
)));

define('KEEN_REVENUE_TIMEFRAMES', serialize(array(
	'previous_30_days' => 'Prior 30 Days',
	'previous_60_days' => 'Prior 60 Days',
	'previous_90_days' => 'Prior 90 Days'
)));

define('US_TIMEZONES', serialize(array(
	'America/New_York' => 'Eastern',
	'America/Chicago' => 'Central',
	'America/Denver' => 'Mountain',
	'America/Phoenix' => 'Mountain (no DST)',
	'America/Los_Angeles' => 'Pacific',
	'America/Anchorage' => 'Alaska',
	'America/Adak' => 'Hawaii',
	'Pacific/Honolulu' => 'Hawaii (no DST)' 
)));

define('NOTIFICATIONS_EMAIL', '1');

define('SEM_PARTNERS', serialize(array('Adwords' => 16, 'Bing' => 23)));
define('LANDER_SOURCE_NAME', serialize(array('get_started' => 'Get Started', 'on' => 'On', 'get_started_2' => 'Get Started-2')));

define('SURVEY_REPORT_REQUEST_PENDING', 0);
define('SURVEY_REPORT_REQUEST_APPROVED', 1);
define('SURVEY_REPORT_REQUEST_REJECTED', 2);


define('SURVEY_REPORT_LATE_NQ_OQ', 1);
define('SURVEY_REPORT_NO_REDIRECT', 2);
define('SURVEY_REPORT_NO_CREDIT', 3);
define('SURVEY_REPORT_INCORRECT_NQ', 4);
define('SURVEY_REPORT_OTHER', 5);

define('SURVEY_REPORT_TYPES', serialize(array(
	SURVEY_REPORT_LATE_NQ_OQ => 'Late disqualification', 
	SURVEY_REPORT_NO_REDIRECT => 'Completed but didn\'t redirect back to MintVine', 
	SURVEY_REPORT_NO_CREDIT => 'Completed but did not credit correctly', 
	SURVEY_REPORT_INCORRECT_NQ => 'Incorrectly reported NQ when success', 
	SURVEY_REPORT_OTHER => 'Other'
)));

define('MAXMIND_PROXY_THRESHOLD', 1.8);
define('IPINTEL_PROXY_THRESHOLD', 0.95);

define('IP_ADDRESS_TYPES', serialize(array(
	'HTTP_CF_CONNECTING_IP' => 8,
	'HTTP_CLIENT_IP' => 1,
	'HTTP_X_FORWARDED_FOR' => 2,
	'HTTP_X_FORWARDED' => 3,
	'HTTP_X_CLUSTER_CLIENT_IP' => 4,
	'HTTP_FORWARDED_FOR' => 5,
	'HTTP_FORWARDED' => 6,
	'REMOTE_ADDR' => 7,
	'HTTP_DISTIL_X_FORWARDED_FOR_OLD' => 9,
	'HTTP_DISTIL_X_FORWARDED_FOR' => 10,
)));

define('PROJECT_PRIORITY_LOW', 10);
define('PROJECT_PRIORITY_NORMAL', 25);
define('PROJECT_PRIORITY_HIGH', 50);
define('PROJECT_PRIORITY_URGENT', 100);

define('PROJECT_PRIORITY_OPTIONS', serialize(array(
	PROJECT_PRIORITY_LOW => 'Low', 
	PROJECT_PRIORITY_NORMAL => 'Normal', 
	PROJECT_PRIORITY_HIGH => 'High', 
	PROJECT_PRIORITY_URGENT => 'Urgent'
)));

define('PRESCREEN_SINGLE', 'single');
define('PRESCREEN_MULTI_EXCLUDES', 'multi.excludes');
define('PRESCREEN_MULTI_INCLUDES', 'multi.includes');

define('PRESCREEN_TYPE_OPTIONS', serialize(array(
	PRESCREEN_SINGLE => 'Single', 
	PRESCREEN_MULTI_EXCLUDES => 'Multi - Any fail excludes', 
	PRESCREEN_MULTI_INCLUDES => 'Multi - Any success continues'
)));

define('QUESTION_TYPE_SINGLE', 'Single Punch');
define('QUESTION_TYPE_MULTIPLE', 'Multi Punch');
define('QUESTION_TYPE_NUMERIC_OPEN_END', 'Numeric - Open-end');
define('QUESTION_TYPE_TEXT_OPEN_END', 'Text - Open-end');
define('QUESTION_TYPE_DUMMY', 'Dummy');

define('QUESTION_TYPES', serialize(array(
	QUESTION_TYPE_SINGLE => 'Single Punch', 
	QUESTION_TYPE_MULTIPLE => 'Multi Punch', 
	QUESTION_TYPE_NUMERIC_OPEN_END => 'Numeric - Open-end', 
	QUESTION_TYPE_TEXT_OPEN_END => 'Text - Open-end', 
	QUESTION_TYPE_DUMMY => 'Dummy', 
)));


define('WITHDRAWAL_NA', 'N/A');
define('WITHDRAWAL_PENDING', 'Pending');
define('WITHDRAWAL_REJECTED', 'Rejected');
define('WITHDRAWAL_PAYOUT_UNPROCESSED', 'Payout Unprocessed');
define('WITHDRAWAL_PAYOUT_SUCCEEDED', 'Payout Succeeded');
define('WITHDRAWAL_PAYOUT_FAILED', 'Payout Failed');

define('WITHDRAWAL_STATUSES', serialize(array(
	WITHDRAWAL_NA => 'N/A',
	WITHDRAWAL_PENDING => 'Pending',
	WITHDRAWAL_REJECTED => 'Rejected',
	WITHDRAWAL_PAYOUT_UNPROCESSED => 'Payout Unprocessed',
	WITHDRAWAL_PAYOUT_SUCCEEDED => 'Payout Succeeded',
	WITHDRAWAL_PAYOUT_FAILED => 'Payout Failed'
))); 

define('QUOTA_TYPE_COMPLETES', 'completes');
define('QUOTA_TYPE_CLICKS', 'clicks');
define('QUOTA_TYPES', serialize(array(
	QUOTA_TYPE_COMPLETES => 'Completes',
	QUOTA_TYPE_CLICKS => 'Clicks',
))); 