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

        $this->RegisterVariableBoolean("State", "State", "~Switch",1);
        $this->RegisterVariableBoolean("StateNebula", "Nebula", "~Switch",5);
        $this->RegisterVariableBoolean("StateStars", "Stars", "~Switch",2);
        $this->RegisterVariableBoolean("Online", "Online", "~Alert.Reversed",0);
        $this->RegisterVariableInteger("IntensityStars", "Intensity Stars", "~Intensity.100",3);
        $this->RegisterVariableInteger("IntensityRotation", "Intensity Rotation", "~Intensity.100",4);
        $this->RegisterVariableInteger("IntensityNebula", "Intensity Nebula", "~Intensity.100",7);
        $this->RegisterVariableInteger("SaturationNebula", "SaturationNebula", "~Intensity.100",8);
        $this->RegisterVariableInteger("ColorNebula", "Color Nebula", "~HexColor",6);
        $this->RegisterVariableString("ColorTuya", "Color Tuya Encoded", "",9);
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

        $this->EnableAction('State');
        $this->EnableAction('StateNebula');
        $this->EnableAction('StateStars');
        $this->EnableAction('IntensityStars');
        $this->EnableAction('IntensityRotation');
        $this->EnableAction('ColorNebula');
        $this->EnableAction('IntensityNebula');
        $this->EnableAction('SaturationNebula');
                
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

        if ($subTopic == 'dps/22/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Laser intensity", 0);

            $intensity = round($Buffer['Payload'] / 10);
            SetValue($this->GetIdForIdent('IntensityStars'), $intensity);            
        }

        if ($subTopic == 'dps/101/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Rotation intensity", 0);

            $intensity = round($Buffer['Payload'] / 10);
            SetValue($this->GetIdForIdent('IntensityRotation'), $intensity);            
        }

        if ($subTopic == 'dps/24/state') {

            $this->SendDebug('MQTT Subtopic Processing', "Color handling", 0);
            SetValue($this->GetIdForIdent('ColorTuya'), $Buffer['Payload']);  

            $hexHue = substr($Buffer['Payload'],0,4);
            $hexSaturation = substr($Buffer['Payload'],4,4);
            $hexValue = substr($Buffer['Payload'],8,4);

            $this->SendDebug('MQTT Subtopic Processing', "Hue: $hexHue Saturation: $hexSaturation Value: $hexValue", 0);

            $hue = hexdec($hexHue);
            $sat = hexdec($hexSaturation) / 10;
            $val = hexdec($hexValue) / 10;

            $rgb = array(0,0,0);
            //calc rgb for 100% SV, go +1 for BR-range
            for($i=0;$i<4;$i++) {
                if (abs($hue - $i*120)<120) {
                $distance = max(60,abs($hue - $i*120));
                $rgb[$i % 3] = 1 - (($distance-60) / 60);
                }
            }

            //desaturate by increasing lower levels
            $max = max($rgb);
            $factor = 255 * ($val/100);

            for($i=0;$i<3;$i++) {
                //use distance between 0 and max (1) and multiply with value
                $rgb[$i] = round(($rgb[$i] + ($max - $rgb[$i]) * (1 - $sat/100)) * $factor);
            }

            $hexColor = sprintf('%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
            
            $this->SendDebug('MQTT Subtopic Processing', "Color Hex: $hexColor", 0);

            SetValue($this->GetIdForIdent('ColorNebula'), hexdec($hexColor));
            SetValue($this->GetIdForIdent('SaturationNebula'), $sat);
            SetValue($this->GetIdForIdent('IntensityNebula'), $val);
        }
    }

    public function RequestAction($ident, $value) {

        switch ($ident) {
		
			case "State":
                $this->MqttSet('dps/20/command', $value ? 'true' : 'false');
				SetValue($this->GetIDForIdent($ident), $value);
				break;
            case "StateNebula":
                if ($value) {
                
                    $this->MqttSet('dps/103/command', 'true');
                }
                else {

                    $this->MqttSet('dps/103/command', 'false');
                }
                SetValue($this->GetIDForIdent($ident), $value);
                break;
            case "StateStars":
                if ($value) {
                
                    $this->MqttSet('dps/102/command', 'true');
                }
                else {

                    $this->MqttSet('dps/102/command', 'false');
                }
                SetValue($this->GetIDForIdent($ident), $value);
                break;
            case "IntensityStars":
                $this->MqttSet('dps/22/command', $value * 10);
                SetValue($this->GetIDForIdent($ident), $value);
                break; 
            case "IntensityRotation":
                $this->MqttSet('dps/101/command', $value * 10);
                SetValue($this->GetIDForIdent($ident), $value);
                break;
            case "ColorNebula":
                $colorTuya = $this->decToTuya($value);
                $this->SendDebug('COLOR CONVERSION',"dec: $value hex: $colorTuya", 0);
                $this->MqttSet('dps/24/command', $colorTuya);
                break;
            case "SaturationNebula":
                $oldColor = GetValue($this->GetIdForIdent('ColorTuya'));
                $h = substr($oldColor,0,4);
                $v = substr($oldColor,8,4);
                $s = sprintf('%04x', $value * 10);
                $newColor = $h . $s . $v;
                $this->MqttSet('dps/24/command', $newColor);
                break;
            case "IntensityNebula":
                $oldColor = GetValue($this->GetIdForIdent('ColorTuya'));
                $h = substr($oldColor,0,4);
                $s = substr($oldColor,4,4);
                $v = sprintf('%04x', $value * 10);
                $newColor = $h . $s . $v;
                $this->MqttSet('dps/24/command', $newColor);
                break;
			default:
				$this->LogMessage("Invalid Ident: $ident", KL_ERROR);
		}
    }

    protected function MqttSet($topic, $payload) {

        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
        $Data['Payload'] = (string)$payload;
        $this->SendDebug('MQTT SEND Topic', $Data['Topic'], 0);
        $this->SendDebug('MQTT SEND Payload', $Data['Payload'], 0);
        $this->SendDataToParent(json_encode($Data));
    }

    // found here: https://stackoverflow.com/a/13887939
    protected function RGBtoHSV($R, $G, $B)    // RGB values:    0-255, 0-255, 0-255
    {                                // HSV values:    0-360, 0-100, 0-100
        // Convert the RGB byte-values to percentages
        $R = ($R / 255);
        $G = ($G / 255);
        $B = ($B / 255);

        // Calculate a few basic values, the maximum value of R,G,B, the
        //   minimum value, and the difference of the two (chroma).
        $maxRGB = max($R, $G, $B);
        $minRGB = min($R, $G, $B);
        $chroma = $maxRGB - $minRGB;

        // Value (also called Brightness) is the easiest component to calculate,
        //   and is simply the highest value among the R,G,B components.
        // We multiply by 100 to turn the decimal into a readable percent value.
        $computedV = 100 * $maxRGB;

        // Special case if hueless (equal parts RGB make black, white, or grays)
        // Note that Hue is technically undefined when chroma is zero, as
        //   attempting to calculate it would cause division by zero (see
        //   below), so most applications simply substitute a Hue of zero.
        // Saturation will always be zero in this case, see below for details.
        if ($chroma == 0)
            return array(0, 0, $computedV);

        // Saturation is also simple to compute, and is simply the chroma
        //   over the Value (or Brightness)
        // Again, multiplied by 100 to get a percentage.
        $computedS = 100 * ($chroma / $maxRGB);

        // Calculate Hue component
        // Hue is calculated on the "chromacity plane", which is represented
        //   as a 2D hexagon, divided into six 60-degree sectors. We calculate
        //   the bisecting angle as a value 0 <= x < 6, that represents which
        //   portion of which sector the line falls on.
        if ($R == $minRGB)
            $h = 3 - (($G - $B) / $chroma);
        elseif ($B == $minRGB)
            $h = 1 - (($R - $G) / $chroma);
        else // $G == $minRGB
            $h = 5 - (($B - $R) / $chroma);

        // After we have the sector position, we multiply it by the size of
        //   each sector's arc (60 degrees) to obtain the angle in degrees.
        $computedH = 60 * $h;

        $this->SendDebug('COLOR CONVERSION RGB->HSV',"R: $R G: $G B: $B / H: $computedH S: $computedS V: $computedV", 0);
        return array($computedH, $computedS, $computedV);
    }

    // From Symcon decimal value to tuya hex encoding
    protected function decToTuya ($decColor) {

	    $decRed = floor($decColor/65536); 
	    $decBlue  = floor(($decColor-($decRed*65536))/256);
	    $decGreen = $decColor-($decBlue*256)-($decRed*65536);

        $decHSV = $this->RGBtoHSV($decRed, $decBlue, $decGreen);

        $hexH = sprintf('%04x', $decHSV[0]);
        $hexS = sprintf('%04x', $decHSV[1] * 10);
        $hexV = sprintf('%04x', $decHSV[2] * 10);

        $colorTuya = $hexH . $hexS . $hexV;

        return $colorTuya;
    }
}
