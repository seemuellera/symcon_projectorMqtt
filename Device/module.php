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
        
        $this->RegisterAttributeString('TopicLookupTable', '');

        $this->RegisterVariableBoolean("State", "State");
        $this->RegisterVariableBoolean("StateNebula", "Nebula");
        $this->RegisterVariableBoolean("StateStars", "Stars");
        $this->RegisterVariableBoolean("Online", "Online");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        
        //Setze Filter für ReceiveData
        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic');
        $Filter = preg_quote($baseTopic);

        $topicLookupTable = Array();
        $topicLookupTable[$baseTopic . '/status'] = "Online";
        $topicLookupTable[$baseTopic . '/dps/20/state'] = "State";
        $topicLookupTable[$baseTopic . '/dps/102/state'] = "StateStars";
        $topicLookupTable[$baseTopic . '/dps/103/state'] = "StateNebula";
        $this->WriteAttributeString('TopicLookupTable', json_encode($topicLookupTable));
        
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

        $Payload = json_decode($Buffer['Payload'], true);
        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';
        $subTopic = str_replace($baseTopic, "", $Buffer['Topic']);
        $this->SendDebug('MQTT Subtopic', $subTopic, 0);

        $topicLookupTable = json_decode($this->ReadAttributeString('TopicLookupTable'), true);
        $variableIdent = $topicLookupTable($Buffer['Topic']);
        $this->SendDebug('Target variable ident', $variableIdent, 0);
    }
}
