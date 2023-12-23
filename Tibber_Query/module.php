<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/functions.php';

	class Tibber extends IPSModule
	{
		use TibberHelper;
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyBoolean("InstanceActive", true);
			$this->RegisterPropertyString("Token", '');
			$this->RegisterPropertyString("Api", 'https://api.tibber.com/v1-beta/gql');
			$this->RegisterPropertyString("Home_ID",'');
			$this->RegisterPropertyBoolean("Price_log", false);
			$this->RegisterPropertyBoolean("DayAhead_Chart", false);
			$this->RegisterPropertyBoolean("Consumption_log", false);
			$this->RegisterPropertyBoolean("Price_Variables", false);
			$this->RegisterPropertyBoolean("Price_ArrayBool", false);
			$this->RegisterPropertyBoolean("Statistics", false);

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

			$this->RegisterProfiles();
			$this->RegisterVariables();
			$this->SetValue('RT_enabled',$this->CheckRealtimeAvailable());
			$this->GetPriceData();
			$this->SetActualPrice();
			
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetStatus(102); // instanz aktiveren
			}
			else
			{
				$this->SetStatus(104); // instanz deaktiveren
			}
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
			$this->Statistics(json_decode($this->PriceArray(), true));

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
						$PRICE_LVL = 0;

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
							case "EXPENSIVE":
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

			// soll verhindern dass das Array wieder eingeschaltet wird, es soll die Funktion TIBBER_PriceArray() verwendet werden.
			if ($this->ReadPropertyBoolean('Price_ArrayBool'))
			{ 
				$jsonform["elements"][3]['items'][2]["visible"] = true;
			}
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

			// DEL fällt mit der nächsten Version raus, stattdessen soll die Funktion benutzt werden TIBBER_PriceArray(int $Instanz)
			if ($this->ReadPropertyBoolean('Price_ArrayBool'))
			{
				$this->SetValue("Price_Array", json_encode($result_array));
			}	

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
					$time_new = mktime(0, 0, 5, intval( date("m") ) , intval(date("d") + 1), intval(date("Y")));
				}
			}
			$timer_new = $time_new - time();
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetTimerInterval("UpdateTimerPrice", $timer_new * 1000);
			}
			else
			{
				$this->SetTimerInterval("UpdateTimerPrice", 0);
			}
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
			if ($this->ReadPropertyBoolean("InstanceActive"))
			{
				$this->SetTimerInterval("UpdateTimerActPrice", $timer_new * 1000);
			}
			else
			{
				$this->SetTimerInterval("UpdateTimerActPrice", 0);
			}
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
					$this->RegisterVariableFloat("PT60M_T0_" . $i, $this->Translate('Today')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), "Tibber.price.cent", 20 + $i);
				}
				for ($i = 0; $i <= 23; $i++) {
					$this->RegisterVariableFloat("PT60M_T1_" . $i, $this->Translate('Tomorrow')." ". $i ." ". $this->Translate('to')." ". ($i + 1) . " ". $this->Translate('h'), "Tibber.price.cent", 50 + $i);
				}
			} 
			else
			{
				for ($i = 0; $i <= 23; $i++) {

					if (@$this->GetIDForIdent("PT60M_T0_" . $i))
					{
						$this->UnregisterVariable("PT60M_T0_" . $i);
					}
				}
				for ($i = 0; $i <= 23; $i++) {
					if (@$this->GetIDForIdent("PT60M_T1_" . $i))
					{
						$this->UnregisterVariable("PT60M_T1_" . $i);
					}
				}
			}
			//$this->RegisterVariableFloat("hourly_consumption", 'Stündlicher Verbrauch', "", 0);
			$this->RegisterVariableFloat("act_price", $this->Translate('actual price'), 'Tibber.price.cent', 0);
			$this->RegisterVariableInteger("act_level", $this->Translate('actual price level'), 'Tibber.price.level', 0);
			$this->RegisterVariableBoolean("RT_enabled", $this->Translate('realtime available'), '', 0);

			// DEL fällt mit einem der nächsten Versionen raus, stattdessen soll die Funktion benutzt werden TIBBER_PriceArray(int $Instanz)
			if ($this->ReadPropertyBoolean('Price_ArrayBool')){
				$this->RegisterVariableString("Price_Array", $this->Translate('Price Array'), "", 0 );
			}
			else
			{
				$this->UnregisterVariable("Price_Array");
			}

			if ($this->ReadPropertyBoolean('Price_log') == true){
				$this->RegisterVariableFloat("Ahead_Price", $this->Translate('day ahead price helper variable'), 'Tibber.price.cent', 0);
				$this->SetLogging();
			}

			// Statistic
			if ($this->ReadPropertyBoolean('Statistics')){

				$archive_handler = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';  //ARchive Handler ermitteln
				$ar = IPS_GetInstanceListByModuleID($archive_handler);
				$ar_id = intval($ar[0]);

				$this->RegisterVariableFloat("minprice", $this->Translate('minimum Price'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minprice"), true );}

				$this->RegisterVariableFloat("maxprice", $this->Translate('maximum Price'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("maxprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("maxprice"), true );}

				$this->RegisterVariableFloat("minmaxprice", $this->Translate('minimum/maximum Price range'), 'Tibber.price.cent', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("minmaxprice")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("minmaxprice"), true );}
				
				$this->RegisterVariableInteger("lowtime", $this->Translate('lowest price at this point in time'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("lowtime")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("lowtime"), true );}
				
				$this->RegisterVariableInteger("hightime", $this->Translate('highest price at this point in time'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("hightime")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("hightime"), true );}
				
				// counter
				$this->RegisterVariableInteger("no_level1", $this->Translate('quantity of very cheapest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level1")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level1"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level1"), 1);}

				$this->RegisterVariableInteger("no_level2", $this->Translate('quantity of cheapest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level2")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level2"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level2"), 1);}

				$this->RegisterVariableInteger("no_level3", $this->Translate('quantity of normal price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level3")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level3"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level3"), 1);}

				$this->RegisterVariableInteger("no_level4", $this->Translate('quantity of highest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level4")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level4"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level4"), 1);}

				$this->RegisterVariableInteger("no_level5", $this->Translate('quantity of very highest price'), '', 0 );
				if (AC_GetLoggingStatus($ar_id, $this->GetIDForIdent("no_level5")) == false){AC_SetLoggingStatus($ar_id,$this->GetIDForIdent("no_level5"), true ); AC_SetAggregationType($ar_id, $this->GetIDForIdent("no_level5"), 1);}

			}
			else
			{
				$this->UnregisterVariable("minprice");
				$this->UnregisterVariable("maxprice");
				$this->UnregisterVariable("minmaxprice");
				$this->UnregisterVariable("lowtime");
				$this->UnregisterVariable("hightime");
				$this->UnregisterVariable("no_level1");
				$this->UnregisterVariable("no_level2");
				$this->UnregisterVariable("no_level3");
				$this->UnregisterVariable("no_level4");
				$this->UnregisterVariable("no_level5");
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
				IPS_SetVariableProfileAssociation('Tibber.price.level', 0, '-', '', 0xFFFFFF);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 1, $this->Translate('very cheap'), '', 0x00FF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 2, $this->Translate('cheap'), '', 0x008000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 3, $this->Translate('normal'), '', 0xFFFF00);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 4, $this->Translate('expensive'), '', 0xFF8000);
				IPS_SetVariableProfileAssociation('Tibber.price.level', 5, $this->Translate('very expensive'), '', 0xFF0000);
			}
			
		}

		public function RequestAction($Ident, $Value)
		{
			switch ($Ident) {
				case "GetHomesData":
					$this->GetHomesData();
				break;
				case "CheckRealtimeEnabled":
					$this->CheckRealtimeAvailable();
				break;
				
			}
		}

		private function Statistics($Data)
		{
			if ($this->ReadPropertyBoolean('Statistics'))
			{
				// Initialisiere der Variablen
				$minPrice = PHP_INT_MAX;
				$minPriceIdent = '';
				$maxPrice = PHP_INT_MIN;
				$maxPriceIdent = '';
				$levelCount = array('VERY_CHEAP'=>0,'CHEAP'=>0,'NORMAL'=>0,'EXPENSIVE'=>0,'VERY_EXPENSIVE'=>0);

				//durchlaufe das Array, um den geringste und höchsten Preis inkl. Stunde (Ident) für morgen zu finden
				for ($i = 24; $i <= 47; $i++)
				{
					$currentPrice = $Data[$i]['Price'];
					//geringster Preis
					if ($currentPrice < $minPrice)
					{
						$minPrice = $currentPrice;
						$minPriceIdent = $Data[$i]['Ident'];
					}
					//höchster Preis
					if ($currentPrice > $maxPrice)
					{
						$maxPrice = $currentPrice;
						$maxPriceIdent = $Data[$i]['Ident'];
					}
				}

					for ($i = 24; $i <= 47; $i++)
					{
						$level = $Data[$i]['Level'];
						if (!empty($level))
						{
							$levelCount[$level]++;
						}
					}
				//gib den geringsten und höchsten Preis aus
				$this->SetValue('minprice', $minPrice);

				$minTime=intval(substr($minPriceIdent, 9)); //Uhrzeit (Stunde), in welcher der niedrigste Preis gilt
				$this->SetValue('lowtime', $minTime);
				
				$this->SetValue('maxprice', $maxPrice);
				$maxTime=intval(substr($maxPriceIdent, 9)); //Uhrzeit (Stunde), in welcher der hächste Preis gilt
				$this->SetValue('hightime', $maxTime);
				$Spanne=$maxPrice-$minPrice;  //Preisspanne zwischen min und max
				$this->SetValue('minmaxprice', $Spanne);
				
				//Zuordnung der Preislevel zu Variablen
				//Anzahl der Preislevel am Folgetag
				$this->SetValue('no_level1', $levelCount['VERY_CHEAP']);
				$this->SetValue('no_level2', $levelCount['CHEAP']);
				$this->SetValue('no_level3', $levelCount['NORMAL']);
				$this->SetValue('no_level4', $levelCount['EXPENSIVE']);
				$this->SetValue('no_level5', $levelCount['VERY_EXPENSIVE']);
			}
		}

		public function PriceArray()
		{
			return $this->ReadAttributeString('Price_Array');
		}
	}