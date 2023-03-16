<?php

declare(strict_types=1);
	class Tibber_Realtime extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RequireParent('{D68FD31F-0E90-7019-F16C-1949BD3079EF}');

			$this->RegisterPropertyBoolean('Active', false);
			$this->RegisterPropertyString("Token", '');
			$this->RegisterPropertyString("Home_ID",'');
			$this->RegisterPropertyString("Api_RT", 'wss://websocket-api.tibber.com/v1-beta/gql/subscriptions');
			$this->RegisterPropertyString("Api", 'https://api.tibber.com/v1-beta/gql');
		
			$this->RegisterAttributeString("Homes", "");
			$this->RegisterAttributeString("Api_RT", "wss://websocket-api.tibber.com/v1-beta/gql/subscriptions");
			$this->RegisterAttributeBoolean("RT_enabled", false);
			$this->RegisterAttributeInteger("Parent_IO", 0);

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
			$this->SendDebug('Variablen', json_encode($Variables),0);
			
			$this->GetRtApi();					//aktuelle Realtime API Adresse abrufen
			$this->ConfigParentIO();
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
			$this->RequireParent('{D68FD31F-0E90-7019-F16C-1949BD3079EF}');

			if ($this->ReadPropertyString("Token") == ''){
				$this->SetStatus(201); // no  token
				$this->CloseIO();
            	return false;
			}
			if ($this->ReadPropertyString("Token") != '' && $this->ReadPropertyString("Home_ID") == ''){
				$this->SetStatus(202); // no  Home selected
				$this->CloseIO();
            	return false;
			}
			$this->CheckRealtimeEnabled();		//Prüfen ob RT enaböled ist für das Home
			$this->GetRtApi();					//aktuelle Realtime API Adresse abrufen
			$this->UpdateParentIOApiURL();		// Bei Bedarf API URL in IO Instanz updaten		
			if (!$this->ReadAttributeBoolean("RT_enabled") ){
				$this->SetStatus(203); // no RT Powermeter ->RT not enabled
				$this->CloseIO();
            	return false;
			}

			$this->SetStatus(102);
			
			$this->RegisterProfiles();
			$this->RegisterVariables();

			$this->RegisterMessageParent();
			$this->OpenIO();
			$this->CloseConnection();
			
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
		public function GetConfigurationForm()
		{
			$jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);

			$value =array();
				$result=$this->ReadAttributeString("Homes");
				$this->SendDebug('Homes-Values_Attribute', json_encode($result),0)	;
				if ($result == '') return;
				$homes = json_decode($result, true);
				$value[] = ["caption"=> "", "value"=> "" ];
				foreach ($homes["data"]["viewer"]["homes"] as $key => $home){
					$value[] = ["caption"=> $home["appNickname"], "value"=> $home["id"] ];
				}
			$this->SendDebug('Homes-Values', json_encode($value),0)	;
			$jsonform["elements"][2]['items'][0]["options"] = $value;
			$this->SendDebug('Homes-Values', json_encode($jsonform),0)	;
			return json_encode($jsonform);
		}

		public function ReceiveData($JSONString)
		{
			$this->SendDebug('Receive Data', $JSONString,0);
			$ar =json_decode($JSONString, true);
			$payload = json_decode($ar['Buffer'], true);

			switch ($payload['type']){

				case 'connection_ack':			// Autorisierung erfolgrteich
					$this->SubscribeData();
					break;
				
				case 'next':					// Antwort Werte
					$this->ProcessReceivedPayload($payload);
					break;

			}
		}
		
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
   		{
        
			switch ($Message) {
				case 10505: /* IM_CHANGESTATUS */
					switch ($Data[0]) {
						case 102: // WebSocket ist aktiv
							$this->SendDebug("Connection", "Tibber WSS Connection open", 0);
							$this->SetStatus(102);
							$this->StartAuthorization();
							break;
						case 104: // WebSocket ist inaktiv
							$this->SendDebug("Connection", "Tibber WSS Connection closed", 0);
							$this->SetStatus(104);
							break;
					}
					break;
			}
   		}

		public function ResetVariables()
		{
			$NewRows = static::$Variables;
			$Variables = [];
			foreach ($NewRows as $Pos => $Variable) {
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
			$this->SendDebug("Variabel_Reset", json_encode($Variables) ,0 );
			IPS_SetProperty($this->InstanceID, 'Variables', json_encode($Variables));
			IPS_ApplyChanges($this->InstanceID);
			return;
		}

		private function CheckRealtimeEnabled()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { features { realTimeConsumptionEnabled } }}}"}';
			$result = $this->CallTibber($request);
			$this->SendDebug('Realtime-Enabled', $result, 0);
			if (!$result) return;		//Bei Fehler abbrechen

			$result_ar = json_decode($result, true);

			$this->WriteAttributeBoolean('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);

		}

		private function GetRtApi()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { websocketSubscriptionUrl }}"}';
			$result = $this->CallTibber($request);
			$this->SendDebug('RT-API-URL', $result, 0);
			if (!$result) return;		//Bei Fehler abbrechen

			$result_ar = json_decode($result, true);

			$this->WriteAttributeString('Api_RT',$result_ar['data']['viewer']['websocketSubscriptionUrl']);

		}

		private function ProcessReceivedPayload(array $payload){

			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				if($Variable['Keep']){
					if (array_key_exists($Variable['Tag'], $payload['payload']['data']['liveMeasurement'])){
						$this->SetValue($Variable['Ident'], $payload['payload']['data']['liveMeasurement'][$Variable['Tag']]);
					}
				}
			}
		}

		private function CallTibber(string $request)
		{
			$headers =  array('Authorization: Bearer '.$this->ReadPropertyString('Token'),  "Content-type: application/json");
			$this->SendDebug('HEADER', json_encode($headers), 0);
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

			$NewRows = static::$Variables;
			$this->SendDebug('Variablen_Reg1', $this->ReadPropertyString('Variables'), 0);
			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				@$this->MaintainVariable($Variable['Ident'], $Variable['Name'], $Variable['VarType'], $Variable['Profile'], $Variable['Pos'], $Variable['Keep']);
				
				foreach ($NewRows as $Index => $Row) {
					if ($Variable['Ident'] == str_replace(' ', '', $Row[1])) {
						unset($NewRows[$Index]);
					}
				}
			}

			if (count($NewRows) != 0) {
				foreach ($NewRows as $NewVariable) {
					$Variables[] = [
					'Pos'          	=> $NewVariable[0],
					'Ident'        	=> str_replace(' ', '', $NewVariable[1]),
					'Name'         	=> $this->Translate($NewVariable[1]),
					'Tag'		   	=> $NewVariable[2],
					'VarType'      	=> $NewVariable[3],
					'Profile'      	=> $NewVariable[4],
					'Factor'       	=> $NewVariable[5],
					'Action'       	=> $NewVariable[6],
					'Keep'         	=> $NewVariable[7],
					];
				}
				IPS_SetProperty($this->InstanceID, 'Variables', json_encode($Variables));
				$this->SendDebug('Variablen Register', json_encode($Variables), 0);
				IPS_ApplyChanges($this->InstanceID);
				return;
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
		private function OpenIO(){
			if ($this->ReadPropertyBoolean('Active')){
				$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
				If (!IPS_GetProperty($io_id, 'Active')){
					IPS_SetProperty($io_id, 'Active', true);
					IPS_ApplyChanges($io_id);
				}
				else{
					$this->SubscribeData();
				}
			}
		}

		private function CloseIO(){
				$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
				IPS_SetProperty($io_id, 'Active', false);
				IPS_ApplyChanges($io_id);
		}
		
		private function StartAuthorization()
		{
			if ($this->ReadPropertyBoolean('Active')){

				$json = '{"type":"connection_init","payload":{"token": "'.$this->ReadPropertyString('Token').'"}}';
				$this->SendTibberRT($json);

			}
		}

		private function ConfigParentIO()
		{
			$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
			$json = '{"Active":false,"Headers":"[{\"Name\":\"Sec-WebSocket-Protocol\",\"Value\":\"graphql-transport-ws\"},{\"Name\":\"user-agent\",\"Value\":\"Symcon_Tibber_Realtime\"}]","URL":"'.$this->ReadAttributeString('Api_RT').'","VerifyCertificate":true}';

			IPS_SetConfiguration($io_id, $json);
			IPS_ApplyChanges($io_id);
			IPS_SetName($io_id, 'Tibber Realtime Webclient');
		}

		private function UpdateParentIOApiURL()
		{
			$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
			$json = IPS_GetConfiguration($io_id);
			$ar = json_decode($json, true);
			if ($ar['URL'] != $this->ReadAttributeString('Api_RT')){		//Wenn neue URL dann IO Instanz updaten
				$this->SendDebug('Realtime API URL', 'Die URL hat sich geändert und wird aktualisiert', 0);
				$ar['URL'] = $this->ReadAttributeString('Api_RT');
				$json = json_encode($ar);

				IPS_SetConfiguration($io_id, $json);
				IPS_ApplyChanges($io_id);
			}
		}

		private function SubscribeData(){
			$tags =' ';
			$Variables = json_decode($this->ReadPropertyString('Variables'), true);
			foreach ($Variables as $pos => $Variable) {
				if($Variable['Keep']){
					$tags .= $Variable['Tag'].' ';
				}
			}	
			$this->SendDebug('Tags-String', $tags, 0);
				
			$json = '{"id":"1","type":"subscribe","payload": {"variables":{},"extensions":{},"query": "subscription{ liveMeasurement(homeId: \"'.$this->ReadPropertyString('Home_ID').'\") {'.$tags.'} }"}}';
			$this->SendDebug('Subscribe-String', $json, 0);
			$this->SendTibberRT($json);
		}

		private function CloseConnection()
		{
			if (!$this->ReadPropertyBoolean('Active')){

				$json = '{"id":"1","type":"complete"}';
				$this->SendTibberRT($json);
				$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
				IPS_SetProperty($io_id, 'Active', false);
				IPS_ApplyChanges($io_id);
			}
		}

		private function RegisterMessageParent()
		{
			$io_id = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
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

		// Mapping Definition für die MQTT Werte - RSCP2MQTT
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
				[ 10	,'minPower'							, 'minPower'						, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Min consumption since midnight (Watt)
				[ 11	,'maxPower'							, 'maxPower'						, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//Peak consumption since midnight (Watt)
				[ 12	,'averagePower'						, 'averagePower'					, VARIABLETYPE_FLOAT, 	'~Watt'					,  1	, false, true],		//AAverage consumption since midnight (Watt)
				[ 13	,'currency'							, 'currency'						, VARIABLETYPE_STRING, 	''						,  1	, false, true],		//Currency of displayed cost; requires active Tibber power dea				
			];
	}