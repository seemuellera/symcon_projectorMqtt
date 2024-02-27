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
                
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        
        $this->SetStatus(102);
    }

    public function getDeviceInfo()
    {

    }
}
