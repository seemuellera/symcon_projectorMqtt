<?php

declare(strict_types=1);

class ProjectorMQTTDevice extends IPSModule
{
    public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'tuya');
        $this->RegisterPropertyString('MQTTTopic', '');

        $this->RegisterVariableBoolean("State", "State", "~Switch");
        $this->RegisterVariableBoolean("StateNebula", "Nebula", "~Switch");
        $this->RegisterVariableBoolean("StateStars", "Stars", "~Switch");
        $this->RegisterVariableBoolean("Online", "Online", "~Alert");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        
        //Setze Filter für ReceiveData
        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic');
        $Filter = preg_quote($baseTopic);
        
        $this->SendDebug('Filter ', '.*' . $Filter . '.*', 0);
        $this->SetReceiveDataFilter('.*' . $Filter . '.*');
                
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        
        $this->SetStatus(102);
    }

    public function getDeviceInfo()
    {
        // TODO: MQTT Call to tuya2mqtt to send all data
        return;
    }

    public function ReceiveData($JSONString)
    {

        if (empty($this->ReadPropertyString('MQTTTopic'))) {

            return false;
        }

        $Buffer = json_decode($JSONString, true);

        $this->SendDebug('MQTT Topic', $Buffer['Topic'], 0);
        $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);

        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';
        $subTopic = str_replace($baseTopic, "", $Buffer['Topic']);
        $this->SendDebug('MQTT Subtopic', $subTopic, 0);

        if ($subTopic == 'status') {

            $this->SendDebug('MQTT Subtopic Processing', "online status", 0);

            if ($Buffer['Payload'] == 'online') {

                SetValue($this->GetIdForIdent('Online'), true);
                $this->SendDebug('MQTT Subtopic Processing', "is online", 0);
            }
            else {

                SetValue($this->GetIdForIdent('Online'), false);
                $this->SendDebug('MQTT Subtopic Processing', "is offline", 0);
            }
        }

        if ($subTopic == 'dps/20/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Global Device state", 0);

            SetValue($this->GetIdForIdent('State'), $Buffer['Payload']);

            
        }

        if ($subTopic == 'dps/102/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Laser state", 0);

            SetValue($this->GetIdForIdent('StateStars'), $Buffer['Payload']);            
        }

        if ($subTopic == 'dps/103/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Nebula state", 0);

            SetValue($this->GetIdForIdent('StateNebula'), $Buffer['Payload']);            
        }
    }
}
