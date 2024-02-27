<?php

declare(strict_types=1);

class Zwave2MQTTDevice extends IPSModule
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

        $this->RegisterVariableBoolean("State", "State");
        $this->RegisterVariableBoolean("StateNebula", "Nebula");
        $this->RegisterVariableBoolean("StateStars", "Stars");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $Filter = preg_quote($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') );
        
        $this->SendDebug('Filter ', '.*' . $Filter . '.*', 0);
        $this->SetReceiveDataFilter('.*' . $Filter . '.*');
        
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        
        $this->SetStatus(102);
    }

    public function getDeviceInfo()
    {

    }
}
