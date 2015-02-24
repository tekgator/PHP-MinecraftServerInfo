<?php

require_once 'MinecraftServerInfoConfig.php';

/**
 * Description of MinecraftServerInfoDns - TODO
 *
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * 
 */
class MinecraftServerInfoDns {
    
    private $connInfo = array();
   
    public function __construct($hostname) {
        $hostNameIdentified = (empty($hostname) ? MinecraftServerInfoConfig::DEFAULT_MINECRAFT_HOSTNAME : $hostname);
        $portIdentified = 0;

        /* check if input contains a port as well */
        $connInfo = explode(':', $hostNameIdentified, 2);
        
        if (array_key_exists(0, $connInfo)) {
            $hostNameIdentified = $connInfo[0];
        }

        if (array_key_exists(1, $connInfo)) {
            $portIdentified = (int) $connInfo[1];
        }
        
        $this->resolveDns($hostNameIdentified, $portIdentified);
    }
    
    private function resolveDns($hostname, $port) {
        $this->connInfo['port'] = (int) $port;        
        
        if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === true) {
            /* hostname provided is a IP adress */
            $this->connInfo['ipadress'] = $hostname;
            $this->connInfo['hostname'] = $this->ipadress;
        } else {
            /* hostname provided is an actual hostname, resolve IP adress */
            $this->connInfo['hostname'] = $hostname;
            $this->connInfo['ipadress'] = gethostbyname($hostname);
        }
        
        if (!filter_var($this->connInfo['hostname'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            $this->connInfo['hostname'] != MinecraftServerInfoConfig::DEFAULT_MINECRAFT_HOSTNAME &&
            $this->connInfo['port'] == 0) {
            /* input is an hostname, but no port submitted, try to resolve via SRV record */
            $this->resolveSRV($this->connInfo['hostname'], $this->connInfo['port']);
        }
        
        if ($this->connInfo['port'] === 0) {
            /* couldn't resolve via SVR record, therefore use default minecraft port */
            $this->connInfo['port'] = MinecraftServerInfoConfig::DEFAULT_MINECRAFT_PORT;
        }
    }
   
    
    private function resolveSRV(&$hostname, &$port) {
        /* try getting the SRV record */
        $result = dns_get_record('_minecraft._tcp.' . $hostname, DNS_SRV);

        if (array_key_exists(0, $result)) {
            if (array_key_exists('target', $result[0])) {
                $hostname = $result[0]['target'];
            }
            if (array_key_exists('port',  $result[0])) {
                $port = $result[0]['port'];
            }
        }        
    }

    public function Get($name) {
        $accessKey = strtolower($name);
        $retVal = false;
        
        if (!empty($accessKey) && array_key_exists($accessKey, $this->connInfo)) {
            $retVal = $this->connInfo[$accessKey];
        }
        
        return $retVal;
    }
    
}
