<?php

trait TibberHelper
{

    private function GetHomesData()
    {
        // Build Request Data
        $request = '{ "query": "{viewer { homes { address { address1 } id appNickname} } }"}';
        $result = $this->CallTibber($request);
        if (!$result) return;		//Bei Fehler abbrechen

        $this->SendDebug(__FUNCTION__, $result, 0);
        $this->WriteAttributeString('Homes', $result);
        $this->GetConfigurationForm();
        $this->ReloadForm();
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

		private function CheckRealtimeAvailable()
		{
			// Build Request Data
			$request = '{ "query": "{viewer { home(id: \"'. $this->ReadPropertyString('Home_ID') .'\") { features { realTimeConsumptionEnabled } }}}"}';
			$result = $this->CallTibber($request);
			$this->SendDebug('Realtime-Enabled', $result, 0);
			if (!$result) return;		//Bei Fehler abbrechen
			$result_ar = json_decode($result, true);

			return $result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled'];
			// RT 		$this->WriteAttributeBoolean('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);
			// query 	$this->SetValue('RT_enabled',$result_ar['data']['viewer']['home']['features']['realTimeConsumptionEnabled']);

		}

}