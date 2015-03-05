<?php

require_once 'MinecraftServerInfoConfig.php';

/**
 * MinecraftServerInfoDns
 * 
 * Class for determination of the hostname, IP adress and 
 * port of a Minecraft server. Input can be:
 *
 * conninfo:port
 * conninfo, port
 * conninfo
 * 
 * whereas connifo can be an IP Adress or Hostname. If nothing is provided
 * the default hostname and default port from the config file is used
 *
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * 
 */
class MinecraftServerInfoDns {
    
    private $ipAdress = '';
    private $hostName = '';
    private $port = 0;
   
    public function __construct($hostname, $port = 0) {
        if ($port === 0) {
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
        } else {
            $hostNameIdentified = $hostname;
            $portIdentified = $port;
        }
            
        $this->resolveDns(htmlspecialchars($hostNameIdentified), $portIdentified);
    }
    
    private function resolveDns($hostname, $port) {
        $this->port = (int) $port;        
        
        if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === true) {
            /* hostname provided is a IP adress */
            $this->ipAdress = $hostname;
            $this->hostName = $this->ipadress;
        } else {
            /* hostname provided is an actual hostname, resolve IP adress */
            $this->hostName = $hostname;
            $this->ipAdress = gethostbyname($hostname);
        }
        
        if (!filter_var($this->hostName, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            $this->hostName != MinecraftServerInfoConfig::DEFAULT_MINECRAFT_HOSTNAME &&
            $this->port == 0) {
            /* input is an hostname, but no port submitted, try to resolve via SRV record */
            $this->resolveSRV($this->hostName, $this->port);
        }
        
        if ($this->port === 0) {
            /* couldn't resolve via SVR record, therefore use default minecraft port */
            $this->port = MinecraftServerInfoConfig::DEFAULT_MINECRAFT_PORT;
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

    /**
     * @return string set or determined IP adress
     */
    public function getIPAdress() {
        return $this->ipAdress;
    }

    /**
     * @return string set or determined hostname adress
     */
    public function  getHostName() {
        return $this->hostName;
    }

    /**
     * @return int set or determined port
     */
    public function  getPort() {
        return $this->port;
    }
    
}
