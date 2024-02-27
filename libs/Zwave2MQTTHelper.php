<?php

declare(strict_types=1);

trait Zwave2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        $variableID = $this->GetIDForIdent($Ident);
        $variableType = IPS_GetVariable($variableID)['VariableType'];

        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';

        $topicConfiguration = $this->getConfigForIdent($Ident);

        if ($topicConfiguration) {

            $this->SetMqttValue($topicConfiguration['topic'], $topicConfiguration['transformation'], $Value);
        }
        else {

            $this->LogError("Receiving data for unconfigure ident: " . $ident, KL_ERROR);
        }
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString, true);

            $this->SendDebug('MQTT Topic', $Buffer['Topic'], 0);
            $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);

            $Payload = json_decode($Buffer['Payload'], true);

            if (is_array($Payload)) {

                $allConfiguredTopics = $this->getConfigTopics();
                $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';
                $subTopic = str_replace($baseTopic, "", $Buffer['Topic']);

                if (in_array($subTopic, $allConfiguredTopics)) {

                    $config = $this->getConfigItemForTopic($subTopic);
                    $this->SendDebug('Extracted data','Fetching config for sub topic ' . $subTopic, 0);

                    if ($config) {

                        //$this->SendDebug('Set Value','Ident: ' . $config['ident'] . ' / ' . $config['transformation'] . ' / ' . $Payload['value'], 0);
                        if (array_key_exists('value', $Payload)) {
                        
                            $this->SetVariableContent($config['ident'], $config['transformation'], $Payload['value']);
                        }
                    }
                    else {
                        $this->LogMessage('Receive Data: Unable to get config item for topic ' . $Buffer['Topic'], KL_ERROR);
                        return;
                    }
                }
                else {

                    $this->SendDebug('Set Value','Topic ' . $subTopic . ' is not a configured topic',0);
                }
            }
        }
    }

    protected function SetVariableContent($ident, $transformation, $value) {

        switch($transformation) {
            
            case "copyValue":
                $this->SetValue($ident, $value);
                break;

            case "divideBy1000":
                $this->SetValue($ident, ($value/1000));
                break;

            case "intToBoolean":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value > 0) {
                    $this->SetValue($ident, true);  
                }
                break;
            
            case "rgbColor":
                $this->SetValue($ident, $this->HexToInt($value));
                break;

            case "dimIntensity":
                // updating the regular var
                if ($value >= 99) {
                    $this->SetValue($ident, 100);
                }
                else {
                    $this->SetValue($ident, $value);
                }

                // updating the virtual switch var
                if ($ident == 'ZWAVE2M_Intensity') {
                    $identSwitch = 'ZWAVE2M_IntensityOnOff';
                }
                if ($ident == 'ZWAVE2M_Intensity_Channel1') {
                    $identSwitch = 'ZWAVE2M_IntensityOnOff_Channel1';
                }
                if ($ident == 'ZWAVE2M_Intensity_Channel2') {
                    $identSwitch = 'ZWAVE2M_IntensityOnOff_Channel2';
                }
                if ($value == 0) {
                    $this->SetValue($identSwitch, false);
                }
                else {
                    $this->SetValue($identSwitch, true);
                }
                break;

            case "protectionRemote":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value == 1) {
                    $this->SetValue($ident, true);  
                }
                break;

            case "protectionLocal":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value == 2) {
                    $this->SetValue($ident, true);  
                }
                break;

            case "dimIntensityOnOff":
                // nothing to do, this will never get called
                break;

            case "ignore":
                // nothing to do
                break;
        }
    }

    protected function SetMqttValue($topic, $transformation, $value) {

        switch($transformation) {
            
            case "copyValue":
                $this->Z2MSet($topic, Array('value' => $value));
                break;

            case "divideBy1000":
                $this->Z2MSet($topic, Array('value' => ($value * 1000)));
                break;

            case "intToBoolean":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 1));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 0));  
                }
                break;
            
            case "rgbColor":
                $this->Z2MSet($topic, Array('value' => $this->IntToHex($value)));
                break;

            case "dimIntensity":
                if ($value >= 100) {
                    $this->Z2MSet($topic, Array('value' => 99));  
                }
                else {
                    $this->Z2MSet($topic, Array('value' => $value));  
                }
                break;

            case "protectionRemote":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 1));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 0));  
                }
                break;

            case "protectionLocal":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 2));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 0));  
                }
                break;
            
            case "dimIntensityOnOff":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => true));    
                }
                else {
                    if ($topic == "38/0/restorePrevious") {
                        $this->Z2MSet("38/0/targetValue", Array('value' => 0));      
                    }
                    if ($topic == "38/1/restorePrevious") {
                        $this->Z2MSet("38/1/targetValue", Array('value' => 0));      
                    }
                    if ($topic == "38/2/restorePrevious") {
                        $this->Z2MSet("38/2/targetValue", Array('value' => 0));      
                    }
                }
                break;

            case "ignore":
                break;
        }
    }

    public function ZWAVE2M_Set($topic, $payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $topic . '/set';
        $Data['Payload'] = $payload;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }

    protected function SetValue($Ident, $Value)
    {
        if (@$this->GetIDForIdent($Ident)) {
            $this->SendDebug('Info :: SetValue for ' . $Ident, 'Value: ' . $Value, 0);
            parent::SetValue($Ident, $Value);
        } else {
            $this->SendDebug('Error :: No Expose for Value', 'Ident: ' . $Ident, 0);
        }
    }
}
