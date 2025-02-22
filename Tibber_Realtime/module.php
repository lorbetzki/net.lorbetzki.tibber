<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/functions.php';

	class Tibber_Realtime extends IPSModule
	{
		use TibberHelper;
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RequireParent('{D68FD31F-0E90-7019-F16C-1949BD3079EF}');

			$this->RegisterPropertyBoolean('Active', false);
			$this->RegisterPropertyString('Token', '');
			$this->RegisterPropertyString('Home_ID','0');
			$this->RegisterPropertyString('Api_RT', 'wss://websocket-api.tibber.com/v1-beta/gql/subscriptions');
			$this->RegisterPropertyString('Api', 'https://api.tibber.com/v1-beta/gql');

			$this->RegisterAttributeString('Homes', '');
			$this->RegisterAttributeString('Api_RT', 'wss://websocket-api.tibber.com/v1-beta/gql/subscriptions');
			$this->RegisterAttributeBoolean('RT_enabled', false);
			$this->RegisterAttributeInteger('Parent_IO', 0);
			$this->RegisterAttributeInteger('WTCounter', 0);

			// Initale Configuration			
			$Variables = [];
        	foreach (static::$Variables as $Pos => $Variable) {
				$Variables[] = [
					'Pos'          	=> $Variable[0],
					'Ident'        	=> str_replace(' ', '', $Variable[1]),
					'Name'         	=> $this->Translate($Variable[1]),
					'Tag'		   	=> $Variable[2],
					'VarType'      	=> $Variable[3],
					'Profile'      	=> $Variable[4],
					'Factor'       	=> $Variable[5],
					'Action'       	=> $Variable[6],
					'Keep'         	=> $Variable[7],
				];
        		}	
				
			$this->RegisterPropertyString('Variables', json_encode($Variables));
			$this->SendDebug(__FUNCTION__,json_encode($Variables),0);
			
			// create unique Tibber ID
			$this->RegisterPropertyInteger('TibberID',rand(1000,9999));

			$this->RegisterMessage(0, IPS_KERNELMESSAGE);
			$this->GetRtApi();					//aktuelle Realtime API Adresse abrufen

			//register watchdogtimer
			$this->RegisterTimer("ReloginSequence", 0, 'TIBBERRT_ReloginSequence($_IPS[\'TARGET\']);');
			$this->RegisterTimer("StartWatchdog", 0, 'TIBBERRT_StartWatchdog($_IPS[\'TARGET\']);');

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
					$this->SetStatus(201); // no  token
					return false;
				}
				if ($this->ReadPropertyString("Token") != '' && $this->ReadPropertyString("Home_ID") == '0'){
					$this->SetStatus(202); // no  Home selected
					$this->GetHomesData();
					return false;
				}
				$this->WriteAttributeBoolean('RT_enabled',$this->CheckRealtimeAvailable());

				$this->GetRtApi();					//aktuelle Realtime API Adresse abrufen
				if (!$this->ReadAttributeBoolean("RT_enabled") ){
					$this->SetStatus(203); // no RT Powermeter ->RT not enabled
					return false;
				}

				$this->SetStatus(102);
				$this->RegisterProfiles();
				$this->RegisterVariables();
  
				$this->RegisterMessageParent();
				$this->UpdateConfigurationForParent();

				// if status not 102, close the connection
				if ( $this->GetStatus() > 200 )
				{
					$this->CloseConnection();
				}
			}

		public function GetConfigurationForm()
		{
			$jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);
			$this->SendDebug(__FUNCTION__,json_encode($jsonform),0);

			$value[] = ["caption"=> "Select Home", "value"=> "0" ];
			$result=$this->ReadAttributeString("Homes");
			$this->SendDebug(__FUNCTION__.' Read Attribute', json_encode($result),0)	;
			if ($result == '') return;
			$homes = json_decode($result, true);
			foreach ($homes["data"]["viewer"]["homes"] as $key => $home){
				if (empty($home["appNickname"]) )
					{	
						$caption = $home['address']['address1']; 
					}
					else
					{
						$caption = $home["appNickname"];
					}
				$value[] = ["caption"=> $caption, "value"=> $home["id"] ];
			}
			$this->SendDebug(__FUNCTION__.' Write Values for Home', json_encode($value),0)	;

			// create Values for List dynamically
			$ListValues = [];
			foreach (static::$Variables as $Pos => $Variable) {
				$Pos          	= $Variable[0];
				$Ident        	= str_replace(' ', '', $Variable[1]);
				$Name         	= $Variable[1];
				$Tag		   	= $Variable[2];
				$VarType      	= $Variable[3];
				$Profile      	= $Variable[4];
				$Factor       	= $Variable[5];
				$Action       	= $Variable[6];
				$Keep         	= $Variable[7];

				$ListValues[] = ["Pos"=>"$Pos", "Ident"=>"$Ident", "Name"=>"$Name", "Tag"=>"$Tag", "VarType"=>"$VarType", "Profile"=>"$Profile", "Factor"=>"$Factor", "Action"=>"$Action", "Keep"=>"$Keep" ];

			}	
				$this->SendDebug(__FUNCTION__.' Write Values for List', json_encode($ListValues),0)	;

				$jsonform["elements"][2]['items'][0]["options"] = $value;
				$jsonform["elements"][2]['items'][0]["visible"] = true;
				$jsonform["elements"][3]['values'] = $ListValues;

				if ($this->ReadPropertyString("Token") && $this->ReadPropertyString("Home_ID") )
				{
					$jsonform["elements"][0]['enabled'] = true;
				}

			return json_encode($jsonform);
		}

		public function ReceiveData($JSONString)
		{
			$ar =json_decode($JSONString, true);
			$payload = json_decode($ar['Buffer'], true);
			$this->SendDebug(__FUNCTION__, 'Payload: '.json_encode($JSONString),0);

			switch ($payload['type']){

				case 'connection_ack':			// Autorisierung erfolgreich
					// if connectionstring is correct, start Subscribing
					$this->SubscribeData();
					$this->SendDebug(__FUNCTION__, "Subscribing", 0);
					break;
				
				case 'next':					// Antwort Werte
					// its a watchdog, if we receive data, we set it to 30 sec. if Watchdog run to 0 we start the relogin sequence
					$this->SetTimerInterval('StartWatchdog', 30000);
					$this->SendDebug(__FUNCTION__, 'reset Watchdog',0);

					// check if we receive a data array in payload, otherwise send message
					if (@is_array($payload['payload']['data']))
					{
						$this->ProcessReceivedPayload($payload);
					}
					else
					{
						$this->LogMessage("Tibber JSON Error :".json_last_error_msg(). " Payload: ". json_encode($payload), KL_ERROR);
					}
					$this->SendDebug(__FUNCTION__, 'Payload: '.json_encode($JSONString),0);

					break;

				case 'error':
					$this->SendDebug(__FUNCTION__, "Error received: ".$JSONString,0);
					break;
				
				case 'connection_init':
					$this->SendDebug(__FUNCTION__, "Error 4408 received: ".$JSONString,0);
					break;
			}
		}
		
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
   		{
			$this->SendDebug(__FUNCTION__, $Message, 0);

			switch ($Message) {
				case IM_CHANGESTATUS: /* IM_CHANGESTATUS 10505 */
					switch ($Data[0]) {
						case 102: // WebSocket ist aktiv
							$this->SendDebug(__FUNCTION__, "Open Werbsocket Connection", 0);
							$this->StartAuthorization();
							
						break;
						case 104: // WebSocket ist inaktiv
							$this->SendDebug(__FUNCTION__, "Close Werbsocket Connection", 0);
							// stop timer if instance will be set inactive
							$this->SetTimerInterval('ReloginSequence', 0);
							$this->SetTimerInterval('StartWatchdog', 0);
							// user can manually close IO without disable instance, but we need to send a closing request
							$this->CloseConnection();
							$this->SetStatus(104);
						break;
					}
				break;
				case KR_READY:
					$this->SendDebug(__FUNCTION__, "Kernel Ready", 0);
					$this->SetTimerInterval('ReloginSequence', 0);
					$this->SetTimerInterval('StartWatchdog', 0);
				break;
	   		}
		}
	

		// allow user to set default values in the configurationform
		private function ResetVariables()
		{
			$Variables = [];
        	foreach (static::$Variables as $Pos => $Variable) {
				$Variables[] = [
					'Pos'          	=> $Variable[0],
					'Ident'        	=> str_replace(' ', '', $Variable[1]),
					'Name'         	=> $this->Translate($Variable[1]),
					'Tag'		   	=> $Variable[2],
					'VarType'      	=> $Variable[3],
					'Profile'      	=> $Variable[4],
					'Factor'       	=> $Variable[5],
					'Action'       	=> $Variable[6],
					'Keep'         	=> $Variable[7],
				];
			}
			$this->SendDebug(__FUNCTION__, json_encode($Variables) ,0 );
			$this->UpdateFormField('Variables', 'values', json_encode($Variables)); 
			return;
		}

		private function GetRtApi()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { websocketSubscriptionUrl }}"}';
			$result = $this->CallTibber($request);
			$this->SendDebug(__FUNCTION__, $result, 0);
			if (!$result) return;		//Bei Fehler abbrechen

			$result_ar = json_decode($result, true);
			$this->WriteAttributeString('Api_RT',$result_ar['data']['viewer']['websocketSubscriptionUrl']);

		}

		private function ProcessReceivedPayload(array $payload){

			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				if($Variable['Keep'] && $Variable['Tag'] != ''){
					if (array_key_exists($Variable['Tag'], $payload['payload']['data']['liveMeasurement'])){
						$this->SetValue($Variable['Ident'], $payload['payload']['data']['liveMeasurement'][$Variable['Tag']]);
					}
				}
			}
			$this->SendDebug(__FUNCTION__, "write Variables ". json_encode($Variables), 0);
			$this->CalcMinMaxPower();
		}

		private function CalcMinMaxPower()
		{
			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				if($Variable['Keep'] && $Variable['Ident'] == 'minPower'){
					if ( !IPS_GetObjectIDByIdent('minPowerConsumption', $this->InstanceID)) return;
					if ( !IPS_GetObjectIDByIdent('maxPowerProduction', $this->InstanceID)) return;
					if ( $this->GetValue('maxPowerProduction') > $this->GetValue('minPowerConsumption') ){
						$this->SetValue('minPower', $this->GetValue('maxPowerProduction') * -1);
					}
					else{
						$this->SetValue('minPower', $this->GetValue('minPowerConsumption') );
					}
				}
				if($Variable['Keep'] && $Variable['Ident'] == 'maxPower'){
					if ( !IPS_GetObjectIDByIdent('maxPowerConsumption', $this->InstanceID)) return;
					if ( !IPS_GetObjectIDByIdent('minPowerProduction', $this->InstanceID)) return;
						$this->SetValue('maxPower', $this->GetValue('maxPowerConsumption') );		
				}
			}
		}

		protected function SendTibberRT($Payload)
		{
			$tibber['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
			$tibber['Buffer'] = $Payload;
			$tibberJSON = json_encode($tibber);
			$this->SendDebug(__FUNCTION__ . '_TIBBER', $Payload, 0);
			$result = @$this->SendDataToParent($tibberJSON);
			$this->SendDebug(__FUNCTION__ . '_TIBBER', $result, 0);

			if ($result === false ) {
				$last_error = error_get_last();
				echo $last_error['message'];
			}
		}

		private function RegisterVariables()
		{
			$this->SendDebug(__FUNCTION__, $this->ReadPropertyString('Variables'), 0);
			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				@$this->MaintainVariable($Variable['Ident'], $Variable['Name'], $Variable['VarType'], $Variable['Profile'], $Variable['Pos'], $Variable['Keep']);
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
			if (!IPS_VariableProfileExists('Tibber.price.euro')) {
				IPS_CreateVariableProfile('Tibber.price.euro', 2);
				IPS_SetVariableProfileIcon('Tibber.price.euro', 'Euro');
				IPS_SetVariableProfileDigits("Tibber.price.euro", 2);
				IPS_SetVariableProfileText("Tibber.price.euro", "", " €");
			}
		}

		private function StartAuthorization()
		{
			if ($this->ReadPropertyBoolean('Active')){

				$json = '{"type":"connection_init","payload":{"token": "'.$this->ReadPropertyString('Token').'"}}';
				$this->SendTibberRT($json);
				$this->SendDebug(__FUNCTION__, $json, 0);
			}
		}

		public function GetConfigurationForParent()
		{
			$Config = [
							"Active"       		 => $this->ReadPropertyBoolean("Active"),
							"URL"       		 => $this->ReadPropertyString("Api_RT"),
							"VerifyCertificate"  => true,
							"Headers"		 	 => "[{\"Name\":\"Sec-WebSocket-Protocol\",\"Value\":\"graphql-transport-ws\"},{\"Name\":\"user-agent\",\"Value\":\"symcon\/6.4 com.tibber\/1.8.3\"}]"
		 			];
			$this->SendDebug(__FUNCTION__, 'Create the Configuration '.json_encode($Config), 0);
			return json_encode($Config);
		}

		Private function UpdateConfigurationForParent()
		{
			$ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
			$this->SendDebug(__FUNCTION__, "ParentID is: ".$ParentId, 0);
			$Script = 'IPS_SetConfiguration(' . $ParentId . ', \'' . $this->GetConfigurationForParent() . '\');' . PHP_EOL;
			$Script .= 'IPS_ApplyChanges(' . $ParentId . ');';
			// triggering MessageSink  IM_CHANGESTATUS .
			IPS_RunScriptText($Script);
		}

		private function SubscribeData(){
			$tags =' ';
			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				if($Variable['Keep'] && $Variable['Tag'] != ''){
					$tags .= $Variable['Tag'].' ';
				}
			}	
			$this->SendDebug(__FUNCTION__,"Tags: ". $tags, 0);
				
			$json = '{"id":"'.$this->ReadPropertyInteger('TibberID').'","type":"subscribe","payload": {"variables":{},"extensions":{},"query": "subscription{ liveMeasurement(homeId: \"'.$this->ReadPropertyString('Home_ID').'\") {'.$tags.'} }"}}';
			$this->SendDebug(__FUNCTION__,"JSON: ". $json, 0);
			$this->SendTibberRT($json);
		}

		private function CloseConnection()
		{
			$json = '{"id":"'.$this->ReadPropertyInteger('TibberID').'","type":"complete"}';
			$this->SendTibberRT($json);
			$this->SendDebug(__FUNCTION__, "send Close Connection request ".json_encode($json), 0);
		}

		private function RegisterMessageParent()
		{
			$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
			$this->SendDebug(__FUNCTION__, "IO ID ".$io_id, 0);

			$act_io_id = $this->ReadAttributeInteger('Parent_IO');
			If ($io_id != $act_io_id){
				if ($act_io_id != 0){
					$this->UnregisterMessage($act_io_id, 10505);
				}
				$this->WriteAttributeInteger('Parent_IO', $io_id);
			}
			$this->RegisterMessage($io_id, 10505);		// IM_CHANGESTATUS des IO Moduls
			return $io_id;
		}

		// Mapping Definition
		private static $Variables = [
			//  POS		IDENT								Tibber TAG							Variablen Typ			Var Profil	  			Faktor  ACTION  KEEP		Comment	
				[ 1		,'power'							, 'power'							, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Consumption at the moment (Watt)
				[ 2		,'powerProduction'					, 'powerProduction'					, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Net production (A-) at the moment (Watt)
				[ 3		,'lastMeterConsumption' 			, 'lastMeterConsumption'			, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//Last meter active import register state (kWh)
				[ 4		,'lastMeterProduction'				, 'lastMeterProduction'				, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//Last meter active export register state (kWh)
				[ 5		,'accumulatedConsumption'			, 'accumulatedConsumption'			, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//kWh consumed since midnight
				[ 6		,'accumulatedProduction'			, 'accumulatedProduction'			, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//net kWh produced since midnight
				[ 7		,'accumulatedConsumptionLastHour'	, 'accumulatedConsumptionLastHour'	, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//kWh consumed since since last hour shift
				[ 8		,'accumulatedProductionLastHour'	, 'accumulatedProductionLastHour'	, VARIABLETYPE_FLOAT, 	'~Electricity'			,  1	, false, true],		//net kWh produced since last hour shift
				[ 9		,'accumulatedCost'					, 'accumulatedCost'					, VARIABLETYPE_FLOAT, 	'Tibber.price.euro'		,  1	, false, true],		//Accumulated cost since midnight; requires active Tibber power deal; includes VAT (where applicable)
				[ 10	,'minPowerConsumption'				, 'minPower'						, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Min consumption since midnight (Watt)
				[ 11	,'maxPowerConsumption'				, 'maxPower'						, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Peak consumption since midnight (Watt)
				[ 12	,'averagePower'						, 'averagePower'					, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//AAverage consumption since midnight (Watt)
				[ 13	,'minPowerProduction'				, 'minPowerProduction'				, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Min net production since midnight (Watt)
				[ 14	,'maxPowerProduction'				, 'maxPowerProduction'				, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Max net production since midnight (Watt)
				[ 15	,'minPower'							, ''								, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//
				[ 16	,'maxPower'							, ''								, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//
				[ 17	,'powerReactive'					, 'powerReactive'					, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Reactive consumption (Q+) at the moment (kVAr)
				[ 18	,'powerProductionReactive'			, 'powerProductionReactive'			, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Net reactive production (Q-) at the moment (kVAr)
				[ 19	,'voltagePhase1'					, 'voltagePhase1'					, VARIABLETYPE_FLOAT, 	'~Volt'					,  1	, false, true],		//Voltage on phase 1
				[ 20	,'voltagePhase2'					, 'voltagePhase2'					, VARIABLETYPE_FLOAT, 	'~Volt'					,  1	, false, true],		//Voltage on phase 2
				[ 21	,'voltagePhase3'					, 'voltagePhase3'					, VARIABLETYPE_FLOAT, 	'~Volt'					,  1	, false, true],		//Voltage on phase 3
				[ 22	,'currentL1'						, 'currentL1'						, VARIABLETYPE_FLOAT, 	'~Ampere'				,  1	, false, true],		//Current on L1
				[ 23	,'currentL2'						, 'currentL2'						, VARIABLETYPE_FLOAT, 	'~Ampere'				,  1	, false, true],		//Current on L2
				[ 24	,'currentL3'						, 'currentL3'						, VARIABLETYPE_FLOAT, 	'~Ampere'				,  1	, false, true],		//Current on L3
				[ 25	,'signalStrength'					, 'signalStrength'					, VARIABLETYPE_INTEGER,	''						,  1	, false, true],		//Device signal strength (Pulse - dB; Watty - percent)				
				[ 30	,'currency'							, 'currency'						, VARIABLETYPE_STRING, 	''						,  1	, false, true],		//Currency of displayed cost; requires active Tibber power dea				

			];

			public function RequestAction($Ident, $Value)
			{
				switch ($Ident) {
					case "GetHomesData":
						$this->GetHomesData();
					break;
					case "ResetVariables":
						$this->ResetVariables();
					break;
				}
			}

			// need a counter to retry only 3 times and give up if we reached this.
			private function ReloginRetriesReached(bool $reset = false)
			{    
				$counter = $this->ReadAttributeInteger('WTCounter');
			   
				if(($counter > 4) OR $reset == true){
					$counter = $this->WriteAttributeInteger('WTCounter',1);
					return true;
				}

				$this->WriteAttributeInteger('WTCounter',($counter + 1));
				return false;
			}

			// Sequence to initiate relogin
			public function ReloginSequence()
			{
				// if the Timer is greater than 0 the Reloingsequence started
				if ($this->GetTimerInterval('ReloginSequence') > 0)
				{
					// lets open the IO
					//$this->OpenIO();
					// stop the Reloginsequence
					$this->SetTimerInterval('ReloginSequence', 0);
					$this->SendDebug(__FUNCTION__, "relogin was occured", 0);
					$this->LogMessage($this->Translate('relogin was occured'), KL_NOTIFY);
					// reset counter to 0
					$this->ReloginRetriesReached(true);
					$this->SetStatus(102);	
					$this->UpdateConfigurationForParent();						
				}
				else
				{	
					// we dont receive data, now we stop tje Watchdog
					$this->SetTimerInterval('StartWatchdog', 0);
					// use a random time between 60-120 sek
					$randomtime = rand(60,120); 
					// set the timer to start ourselve again
					$this->SetTimerInterval('ReloginSequence', $randomtime * 1000);
					$this->SendDebug(__FUNCTION__, "relogin sequence is initiated in " . $randomtime ." sec.", 0);
					$this->LogMessage($this->Translate('relogin sequence is initiated in ') . $randomtime . $this->Translate('sec.'), KL_NOTIFY);
					// count relogins, after three times we received a true and can abort it
					$counter = $this->ReloginRetriesReached();
					//$this->CloseIO();
					if ($counter)
					{
						$this->SendDebug(__FUNCTION__, "relogin aborted, max retries reached", 0);
						$this->LogMessage($this->Translate('relogin aborted, max retries reached'), KL_NOTIFY);
						// to abort we stop this ReloginSequence and the status to the instance
						$this->SetTimerInterval('ReloginSequence', 0);
						$this->SetStatus(104);							
					}
				}
			}

			// would be use in ReceiveData(), if the counter run to 0 we start the reloginsequence
			public function StartWatchdog()
			{
				$this->SendDebug(__FUNCTION__, "No data received, starting relogin sequence", 0);
				$this->ReloginSequence();
			}

}