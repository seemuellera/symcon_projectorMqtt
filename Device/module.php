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
        $this->RegisterPropertyInteger('MQTTServer', 19143);
        $this->RegisterPropertyString('MQTTBaseTopic', 'tuya');
        $this->RegisterPropertyString('MQTTTopic', '');

        $this->RegisterVariableBoolean("State", "State");
        $this->RegisterVariableBoolean("StateNebula", "Nebula");
        $this->RegisterVariableBoolean("StateStars", "Stars");
        $this->RegisterVariableBoolean("Online", "Online");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
                
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        
        $this->SetStatus(102);
    }

    public function getDeviceInfo()
    {

        $stateGlobal = MQTT_GetRetainedMessage($this->ReadPropertyInteger('MQTTServer'), $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/dps/20/state');
        print_r($stateGlobal);
    }
}
