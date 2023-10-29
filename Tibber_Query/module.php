<?php

declare(strict_types=1);
	class Tibber extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("Token", '');
			$this->RegisterPropertyString("Home_ID",'');
			$this->RegisterPropertyString("Api", 'https://api.tibber.com/v1-beta/gql');
			$this->RegisterPropertyBoolean("Price_log", false);
			$this->RegisterPropertyBoolean("DayAhead_Chart", false);
			$this->RegisterPropertyBoolean("Consumption_log", false);
			$this->RegisterPropertyBoolean("Price_Variables", false);
			$this->RegisterPropertyBoolean("Price_Array", false);
			
			$this->RegisterAttributeString("Homes", "");
			$this->RegisterAttributeString("Price_Array", '');
			$this->RegisterAttributeInteger("ar_handler", 0);
			$this->RegisterAttributeBoolean("EEX_Received", false);
			
			//--- Register Timer
			$this->RegisterTimer("UpdateTimerPrice", 0, 'TIBBER_GetPriceData($_IPS[\'TARGET\']);');
			$this->RegisterTimer("UpdateTimerActPrice", 0, 'TIBBER_SetActualPrice($_IPS[\'TARGET\']);');
			
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			if ($this->ReadPropertyString("Token") == ''){
				$this->SetStatus(201); // Kein Token
            	return false;
			}
			if ($this->ReadPropertyString("Token") != '' && $this->ReadPropertyString("Home_ID") == ''){
				$this->SetStatus(202); // Kein Zuhause
				$this->GetHomesData();
            	return false;
			}

			

			$this->SetStatus(102);
			$this->RegisterProfiles();
			$this->RegisterVariables();
			$this->CheckRealtimeEnabled();
			$this->GetPriceData();
			$this->SetActualPrice();

		}
		public function GetHomesData()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { homes { id appNickname} } }"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen

			$this->SendDebug("Homes_Result", $result, 0);
			$this->WriteAttributeString('Homes', $result);
			$this->GetConfigurationForm();
			$this->ReloadForm();

		}
		public function GetPriceData()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { currentSubscription { priceInfo { today { total energy tax startsAt level } tomorrow { total energy tax startsAt level }}}}}}"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen

			$this->SendDebug("Price_Result", $result, 0);
			$this->ProcessPriceData($result, );
			$this->SetUpdateTimerPrices();

		}

		public function GetConsumptionHourly()
		{
			$this->GetConsumptionData('HOURLY');
		}

		public function GetConsumptionDaily()
		{
			$this->GetConsumptionData('DAILY');
		}

		public function GetConsumptionWeekyl()
		{
			$this->GetConsumptionData('WEEKLY');
		}

		public function GetConsumptionMonthly()
		{
			$this->GetConsumptionData('MONTHLY');
		}

		public function GetConsumptionYearly()
		{
			$this->GetConsumptionData('ANNUAL');
		}

		public function SetActualPrice(){
			date_default_timezone_set('Europe/Berlin');
			if ($this->ReadAttributeString("Price_Array") == ''){
				$this->GetPriceData();
			}
			if ($this->ReadAttributeString("Price_Array") != ''){
				$prices = json_decode($this->ReadAttributeString("Price_Array"),true);

				
				$h = date('G');
				foreach ( $prices as $wa_price ){
					$hour = substr($wa_price["Ident"],9);
					$day  = substr($wa_price["Ident"],6,2);
					if ( $hour == $h && $day == 'T0'){
						$this->SetValue('act_price' , $wa_price["Price"]);	

						switch($wa_price["Level"])
						{
							case "VERY_CHEAP":
								$PRICE_LVL = 1;
							break;
							case "CHEAP":
								$PRICE_LVL = 2;
							break;
							case "NORMAL":
								$PRICE_LVL = 3;
							break;
							case "":
								$PRICE_LVL = 4;
							break;
							case "VERY_EXPENSIVE":
								$PRICE_LVL = 5;
							break;
						}

						$this->SetValue('act_level', $PRICE_LVL );	
					}
				}
				$this->SetUpdateTimerActualPrice();
			}
		}

		public function CheckRealtimeEnabled()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { features { realTimeConsumptionEnabled } }}}"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen
			$result_ar = json_decode($result, true);

			$this->SetValue('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);

		}

		public function GetConfigurationForm()
		{
			$jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);

			$value =array();
				$result=$this->ReadAttributeString("Homes");
				$this->SendDebug("Form_homes", $result, 0);
				if ($result == '') return;
				$homes = json_decode($result, true);
				$value[] = ["caption"=> "", "value"=> "" ];
				foreach ($homes["data"]["viewer"]["homes"] as $key => $home){
					$value[] = ["caption"=> $home["appNickname"], "value"=> $home["id"] ];
				}
			$jsonform["elements"][1]['items'][0]["options"] = $value;
			$jsonform["elements"][1]['items'][0]["visible"] = true;
			IPS_SetProperty($this->InstanceID, 'Home_ID', $value[0]["value"] );
			return json_encode($jsonform);
		}

		private function GetConsumptionData(string $timing)
		{
			// Build Request Data
			$count = 10;
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { consumption(resolution: '.$timing.', last: '.$count.') { nodes { from to cost unitPrice unitPriceVAT consumption consumptionUnit }}}}}"}';
			$result = $this->CallTibber($request);
			if (!$result) return;		//Bei Fehler abbrechen

			$this->SendDebug("Consumption_Result", $result, 0);
			//$this->process_consumption_data($result, $timing);

		}

		private function ProcessConsumptionData(string $result, string $timing)
		{
			$log_consum = '';
			$log_price = '';
			$log_costs = '';
			$con = json_decode($result, true);

			switch ($timing){
				case "HOURLY":	
					$log_consum = 'hourly_consumption';	
					$log_price =  'hourly_price';	
					$log_costs =  'hourly_costs';	
				case "DAILY":	
					$log_consum = 'daily_consumption';
					$log_price = 'daily_price';
					$log_costs = 'daily_costs';	
				case "WEEKLY":	
					$log_consum = 'weekly_consumption';
					$log_price = 'weekly_price';
					$log_costs = 'weekly_costs';	
				case "MONTHLY":	
					$log_consum = 'monthly_consumption';
					$log_price = 'monthly_price';
					$log_costs = 'monthly_costs';	
				case "ANNUAL":	
					$log_consum = 'annual_consumption';
					$log_price = 'annual_price';
					$log_costs = 'annual_costs';	
			}

			foreach ($con["data"]["viewer"]["home"]["consumption"]["nodes"] AS $key => $wa_con) {
				
				$start = strtotime($wa_con["from"]);
				$end = strtotime($wa_con["from"]) - 1; 
				// Consumption Update
					AC_DeleteVariableData($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum), $start, $end);
					$last_log= AC_GetLoggedValues($this->ReadAttributeInteger("ar_handler"),$this->GetIDForIdent($log_consum),$start - 1, $start -1, 1 )[0]['Value'];
					if ($last_log != ''){ }
				AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum), [[ 'TimeStamp' => $end, 'Value' => $wa_con["consumption"] ]]);	
			}
				AC_ReAggregateVariable($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent($log_consum));	

		}

		private function ProcessPriceData(string $result)
		{
			$t1 = false;
			$result_array = [];
			$prices = json_decode($result, true);

			foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["today"] AS $key => $wa_price) {
				
				$var = 'PT60M_T0_'.$key;
				$this->SetPriceVariables($var, $wa_price);
				$result_array[] = [ 'Ident' => $var,
									'Price' => $wa_price['total'] * 100,
									'Level' => $wa_price['level']];

			}
			foreach ($prices["data"]["viewer"]["home"]["currentSubscription"]["priceInfo"]["tomorrow"] AS $key => $wa_price) {
				
				$t1 = true;
				$var = 'PT60M_T1_'.$key;
				$this->SetPriceVariables($var, $wa_price);
				$result_array[] = [ 'Ident' => $var,
									'Price' => $wa_price['total'] * 100,
									'Level' => $wa_price['level']];

			}

			if (!$t1){
				for ($i = 0; $i <= 23; $i++) {
					$var = 'PT60M_T1_'.$i;
				$this->SetPriceVariablesZero($var);
				$result_array[] = [ 'Ident' => $var,
									'Price' => 0,
									'Level'	=> ''];
				}
				$this->WriteAttributeBoolean('EEX_Received', false);
			}
			else{
				$this->WriteAttributeBoolean('EEX_Received', true);
			}
			
       		$this->WriteAttributeString("Price_Array", json_encode($result_array));
			$this->SetValue("Price_Array", json_encode($result_array));
			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->LogAheadPrices($result_array);
			}
		}

		private function LogAheadPrices($result_array)
		{
			date_default_timezone_set('Europe/Berlin');
			$start = mktime(0, 0, 0, intval( date("m") ) , intval(date("d")-2), intval(date("Y")));
			$end = mktime(23, 59, 59, intval( date("m") ) , intval(date("d")-1), intval(date("Y")));

			AC_DeleteVariableData($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), $start, $end);

			foreach ( $result_array as $Pos => $res ){
				if ( substr($res["Ident"],7,1) == 0 ) {
					$hour = intval(substr($res["Ident"],9));
					AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime($hour, 00, 01, intval( date("m") ) , intval(date("d")-2), intval(date("Y"))), 'Value' => $res["Price"] ]]);
				}
				elseif ( substr($res["Ident"],7,1) == 1 ){
					$hour = intval(substr($res["Ident"],9));
					AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime($hour, 00, 01, intval( date("m") ) , intval(date("d")-1), intval(date("Y"))), 'Value' => $res["Price"] ]]);
				}
			}
			$count = count($result_array);
			$this->SendDebug('Result_array', $count, 0);
			if ($count <= 24){
				AC_AddLoggedValues($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"), [[ 'TimeStamp' => mktime(00, 00, 01, intval( date("m") ) , intval(date("d")-1), intval(date("Y"))), 'Value' => 0 ]]);
			}
			AC_ReAggregateVariable($this->ReadAttributeInteger("ar_handler"), $this->GetIDForIdent("Ahead_Price"));
		}


		private function CallTibber(string $request)
		{
			$headers =  array('Authorization: Bearer '.$this->ReadPropertyString('Token'),  "Content-type: application/json");
			$curl = curl_init();

			curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, $this->ReadPropertyString('Api'));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  $request  );

			$result = curl_exec($curl);   
			$this->SendDebug('Call_tibber_result', $result,0);
            curl_close($curl);

			$ar = json_decode($result, true); 

			if (array_key_exists('errors', $ar)){
				switch ($ar['errors'][0]['message']){
					case 'Context creation failed: invalid token':
						$this->SetStatus(210);
						return false;
						break;

					default:
						return false;
						break;
				}
			}
			if (array_key_exists('data', $ar)){
				return $result;
			}
		}


		private function SetLogging()
		{
			$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';  //ARchive Handler ermitteln
			$ar = IPS_GetInstanceListByModuleID($archive_handler);
			$ar_id = intval($ar[0]);
			$this->WriteAttributeInteger("ar_handler", $ar_id);

			$status = AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("Ahead_Price"));
			if ($status == false){
				AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("Ahead_Price"), true );
			}
			unset($status);
			
			$status = AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("act_price"));
			if ($status == false){
				AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("act_price"), true );
			}
			unset($status);
			
			$this->CreateAheadChart();
		}
		
		private function CreateAheadChart()
		{
			if (!@$this->GetIDForIdent('TIBBER_Day_Ahead_Chart')){
				$var = $this->GetIDForIdent('Ahead_Price');
				$id = IPS_CreateMedia(4);
				IPS_SetParent($id,  $this->InstanceID);
				$payload = '{"datasets":[{"variableID":'.$var.',"fillColor":"#669c35","strokeColor":"#77bb41","timeOffset":-2,"visible":true,"title":"Preis Heute","type":"bar","side":"left"},{"variableID":'.$var.',"fillColor":"#f2f7b7","strokeColor":"#f2f7b7","timeOffset":-1,"visible":true,"title":"Preis Morgen","type":"bar","side":"left"}]}';
				IPS_SetMediaFile($id,IPS_GetKernelDir().join(DIRECTORY_SEPARATOR, array("media", $id.".chart")),0);
				IPS_SetMediaContent($id, base64_encode($payload));
				IPS_SetName($id,'Day Ahead Chart');	
				IPS_SetIdent($id, 'TIBBER_Day_Ahead_Chart') ;
				IPS_SetPosition($id, 200);
			}
		}

		private function SetPriceVariables(string $var, array $wa_price)
		{	
			if ($this->ReadPropertyBoolean('Price_Variables')){
				$this->setvalue($var, $wa_price['total'] *100);
			}
		}

		private function SetPriceVariablesZero(string $var)
		{	
			if ($this->ReadPropertyBoolean('Price_Variables')){
				$this->setvalue($var, 0 );
			}
		}

		private function SetUpdateTimerPrices()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <13){
				$time_new = mktime(13, 0, 0, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				if (!$this->ReadAttributeBoolean('EEX_Received')){
					$time_new = time() + 300;								// Alle 5 Minuten abholen bis T1 Wert geliefert wird.
				}
				else{
					$time_new = mktime(0, 0, 15, intval( date("m") ) , intval(date("d") + 1), intval(date("Y")));
				}
			}
			$timer_new = $time_new - time();
			$this->SetTimerInterval("UpdateTimerPrice", $timer_new * 1000);
			$this->SendDebug('Price Timer - Rundate', date('c', $time_new),0);
			$this->SendDebug('Price Timer - Run in sec', $timer_new ,0);
		}
		private function SetUpdateTimerActualPrice()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <23){
				$time_new = mktime($h+1, 0, 01, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				$time_new = mktime(0, 0, 10, intval( date("m") ) , intval(date("d")+1), intval(date("Y")));
			}
			$timer_new = $time_new - time();
			$this->SetTimerInterval("UpdateTimerActPrice", $timer_new * 1000);
			$this->SendDebug('Act-Price Timer - Rundate', date('c', $time_new),0);
			$this->SendDebug('Act-Price Timer - Run in sec', $timer_new ,0);
		}

		private function CalcNewDay()
		{
			date_default_timezone_set('Europe/Berlin');
			$date_new = mktime(0, 0, 01, intval( date("m") ) , intval(date("d")+1), intval(date("Y")));
			$act_date = time();
			return $date_new - $act_date;


		}
		private function CalcNewHour()
		{
			date_default_timezone_set('Europe/Berlin');
			$h = date('G');
			if ($h <23){
				$h = date('G') +1;
				$date_new = mktime($h, 0, 01, intval( date("m") ) , intval(date("d")), intval(date("Y")));
			}
			else{
				$date_new = time() + 3600;
			}
			$act_date = time();
			return $date_new - $act_date;


		}
		private function RegisterVariables()
		{
			if ($this->ReadPropertyBoolean('Price_Variables')){
				for ($i = 0; $i <= 23; $i++) {
					$this->RegisterVariableFloat("PT60M_T0_" . $i, "Heute " . $i . " bis " . ($i + 1) . " Uhr", "Tibber.price.cent", 20 + $i);
				}
				for ($i = 0; $i <= 23; $i++) {
					$this->RegisterVariableFloat("PT60M_T1_" . $i, "Morgen " . $i . " bis " . ($i + 1) . " Uhr", "Tibber.price.cent", 50 + $i);
				}
			}
			//$this->RegisterVariableFloat("hourly_consumption", 'Stündlicher Verbrauch', "", 0);
			$this->RegisterVariableFloat("act_price", 'Aktueller Preis', 'Tibber.price.cent', 0);
			$this->RegisterVariableInteger("act_level", 'Aktueller Preis Level', 'Tibber.price.level', 0);
			$this->RegisterVariableFloat("Ahead_Price", 'Day Ahead Preis', 'Tibber.price.cent', 0);
			$this->RegisterVariableBoolean("RT_enabled", 'Realtime verfügbar', '', 0);

			$this->RegisterVariableString("Price_Array"		, "Preis_Array", "", 0 );

			
			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->SetLogging();
			}
			
		}

		private function RegisterProfiles()
		{
			if (!IPS_VariableProfileExists('Tibber.price.cent')) {
				IPS_CreateVariableProfile('Tibber.price.cent', 2);
				IPS_SetVariableProfileIcon('Tibber.price.cent', 'Euro');
				IPS_SetVariableProfileDigits("Tibber.price.cent", 2);
				IPS_SetVariableProfileText("Tibber.price.cent", "", " Cent");
			}
			
			if (!IPS_VariableProfileExists('Tibber.price.level')) {
				IPS_CreateVariableProfile('Tibber.price.level', 1);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 1, $this->Translate('very cheap'), '', 0x00FF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 2, $this->Translate('cheap'), '', 0x008000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 3, $this->Translate('normal'), '', 0xFFFF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 4, $this->Translate('expensive'), '', 0xFF8000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 5, $this->Translate('very expensive'), '', 0xFF0000);
			}
			
		}
	}