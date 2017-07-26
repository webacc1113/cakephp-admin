<?php
class TimezoneHelper extends AppHelper {
	
	function show() {
		$zones = array(
			'Pacific/Samoa'            	=> 'Apia, Upolu, Samoa',                      // UTC-11:00
			'Pacific/Honolulu'         	=> 'Honolulu, Oahu, Hawaii, United States',   // UTC-10:00
			'America/Juneau'           	=> 'Anchorage, Alaska, United States',        // UTC-09:00
			'America/Los_Angeles'      	=> 'Los Angeles, California, United States',  // UTC-08:00
			'America/Phoenix'			=> 'Phoenix, Arizona, United States',         // UTC-07:00
			'America/Chicago'           => 'Chicago, Illinois, United States',        // UTC-06:00
			'America/New_York'          => 'New York City, United States',            // UTC-05:00
			'America/Santiago'         => 'Santiago, Chile',                         // UTC-04:00
			'America/Sao_Paulo'        => 'São Paulo, Brazil',                       // UTC-03:00
			'Atlantic/South_Georgia'   => 'South Georgia, S. Sandwich Islands',      // UTC-02:00
			'Atlantic/Cape_Verde'      => 'Praia, Cape Verde',                       // UTC-01:00
			'Europe/London'            => 'London, United Kingdom',                  // UTC+00:00
			'UTC'                      => 'Universal Coordinated Time (UTC)',        // UTC+00:00
			'Europe/Paris'             => 'Paris, France',                           // UTC+01:00
			'Africa/Cairo'             => 'Cairo, Egypt',                            // UTC+02:00
			'Europe/Moscow'            => 'Moscow, Russia',                          // UTC+03:00
			'Asia/Dubai'               => 'Dubai, United Arab Emirates',             // UTC+04:00
			'Asia/Karachi'             => 'Karachi, Pakistan',                       // UTC+05:00
			'Asia/Dhaka'               => 'Dhaka, Bangladesh',                       // UTC+06:00
			'Asia/Jakarta'             => 'Jakarta, Indonesia',                      // UTC+07:00
			'Asia/Hong_Kong'           => 'Hong Kong, China',                        // UTC+08:00
			'Asia/Tokyo'               => 'Tokyo, Japan',                            // UTC+09:00
			'Australia/Sydney'         => 'Sydney, Australia',                       // UTC+10:00
			'Pacific/Noumea'           => 'Nouméa, New Caledonia, France',           // UTC+11:00
		);
		$dateTime = new DateTime('now');
		foreach($zones as $zone => $name) {
			$zoneObject = new DateTimeZone($zone);
			$dateTime->setTimezone($zoneObject);
			$zones[$zone] = $dateTime->format('g:i A - ').$name;
		}
		return $zones;
	}
}
