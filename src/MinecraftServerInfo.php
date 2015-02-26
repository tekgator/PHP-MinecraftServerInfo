<?php

require_once 'core/MinecraftServerInfoConfig.php';
require_once 'core/MinecraftServerInfoDns.php';
require_once 'core/MinecraftServerInfoTcpPing.php';

/**
 * MinecraftServerInfo
 * Class to query information from a Minecraft server
 * 
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * @link http://wiki.vg/Server_List_Ping Protocol description
 * @version 1.2
 * 
 */
class MinecraftServerInfo {
    
    private $mcDns = null;
    private $serverInfo = ['isOnline'           => false,
                           'response'           => '',
                           'decodedResponse'    => '',
                           'latency'            => 0,
                           'lastError'          => 'server not queried'];

    
    /**
     * @param string $hostname Hostname:Port of minecraft server
     * @param string $port     Port of minecraft server 
     */
    public function __construct($hostname = '', $port = 0) {
        $this->mcDns = new MinecraftServerInfoDns($hostname, $port);
    }
    
    /**
     * query or refresh minecraft server info
     * @return bool returns whether server is on/offline
     */
    public function query() {
        $mcQuery = new MinecraftServerInfoTcpPing($this->mcDns);
        
        $this->serverInfo = ['isOnline'     => $mcQuery->isOnline(),
                             'response'     => $mcQuery->getResponse(),
                             'latency'      => $mcQuery->getLatency(),
                             'lastError'    => empty($mcQuery->getLastError()) ? false : $mcQuery->getLastError()];
        
        $this->serverInfo['decodedResponse'] = json_decode($mcQuery->GetResponse(), true);
        if (is_string($this->serverInfo['decodedResponse'])) {
            $this->serverInfo['isOnline'] = false;
            $this->serverInfo['lastError'] = $this->serverInfo['decodedResponse'];
        } elseif (!is_array($this->serverInfo['decodedResponse'])) {
            $this->serverInfo['isOnline'] = false;
            $this->serverInfo['lastError'] = 'Error occured while decoding server response.' .
                    ' /errNo=' . json_last_error() .
                    ' /errMsg=' . json_last_error_msg();
        }
        
        return $this->isOnline();
    }

    /**
     * @param bool $decodedVersion Whether to return the raw or decoded version
     * @return string|array|bool Returns the response from the server
     */
    public function getResponse($decodedVersion = true) {
        $retVal = false;
        if ($this->isOnline()) {
            if ($decodedVersion && array_key_exists('decodedResponse', $this->serverInfo)) {
                $retVal = $this->serverInfo['decodedResponse'];
            } else {
                $retVal = $this->serverInfo['response'];
            }
        }
        return $retVal;
    }    
    
    /**
     * @return string Server DNS Adress
     */
    public function getHostName() {
        return $this->mcDns->getHostName();
    }
        
    /**
     * @return string Server IP Adress
     */
    public function getIPAdress() {
        return $this->mcDns->getIPAdress();
    }

    /**
     * @return string Server IP Adress
     */
    public function getPort() {
        return $this->mcDns->getPort();
    }

    /**
     * @return bool Server is on/offline
     */
    public function isOnline() {
        return $this->serverInfo['isOnline'];
    }

    /**
     * @return int latency time to server in ms
     */
    public function getLatency() {
        return $this->serverInfo['latency'];
    }
    
    /**
     * @return string last error occured during querying 
     *                the server or decoding the data received
     */
    public function getLastError() {
        return $this->serverInfo['lastError'];
    }
    
    /**
     * @return string|bool Server software and version (e.g. Vanilla 1.8)
     */
    public function getVersion() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('version', $this->serverInfo['decodedResponse']) && 
                array_key_exists('name', $this->serverInfo['decodedResponse']['version'])) {            
                $retVal = $this->serverInfo['decodedResponse']['version']['name'];
                
                if (is_numeric($retVal[0])) {
                    if ($this->getModType() == 'FML') {
                        $retVal = 'ForgeMod ' . $retVal;
                    } else {
                        $retVal = 'Vanilla ' . $retVal;
                    }
                }
            }
        }
        return $retVal;
    }    

    /**
     * @return string|bool Server protocol version
     */
    public function getProtocolVersion() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('version', $this->serverInfo['decodedResponse']) && 
                array_key_exists('protocol', $this->serverInfo['decodedResponse']['version'])) {            
                $retVal = $this->serverInfo['decodedResponse']['version']['protocol'];
            }
        }
        return $retVal;
    }    
    
    /**
     * @return string|bool  Message of the day / Description
     */
    public function getMotd() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('description', $this->serverInfo['decodedResponse'])) {            
                $retVal = '';
                if (is_array($this->serverInfo['decodedResponse']['description'])) {
                    foreach($this->serverInfo['decodedResponse']['description'] as $description) {
                        $retVal .= $description;
                    }
                } else {
                    $retVal = $this->serverInfo['decodedResponse']['description'];
                }
            }
        }
        return $retVal;
    }     
   
    /**
     * @return int|bool Max. count of player possible on the server
     */
    public function getMaxPlayerCount() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('players', $this->serverInfo['decodedResponse']) && 
                array_key_exists('max', $this->serverInfo['decodedResponse']['players'])) {            
                $retVal = $this->serverInfo['decodedResponse']['players']['max'];
            }
        }
        return $retVal;
    }     
    
    /**
     * @return int|bool Max. Current count of player online on the server
     */
    public function getOnlinePlayerCount() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('players', $this->serverInfo['decodedResponse']) && 
                array_key_exists('online', $this->serverInfo['decodedResponse']['players'])) {            
                $retVal = $this->serverInfo['decodedResponse']['players']['online'];
            }
        }
        return $retVal;
    }       

    /**
     * NOTE: returns a portion of online players only
     * @param bool $includeUUID whether the UUID should be included
     * @return array|bool Array of online player names ('name') and UUID ('id') 
     */
    public function getOnlinePlayers($includeUUID = false) {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('players', $this->serverInfo['decodedResponse']) && 
                array_key_exists('sample', $this->serverInfo['decodedResponse']['players'])) {            
                if ($includeUUID) {
                    $retVal = $this->serverInfo['decodedResponse']['players']['sample'];
                } else {
                    $retVal = array();
                    foreach($this->serverInfo['decodedResponse']['players']['sample'] as $player) {
                        $retVal[] = $player['name'];
                    }
                }
            }
        }
        return $retVal;
    }       

    /**
     * @return string|bool  Base64 string of the server icon (can be used within src attribute of img tag)
     */
    public function getFavIcon() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('favicon', $this->serverInfo['decodedResponse'])) {            
                $retVal = $this->serverInfo['decodedResponse']['favicon'];
            }
        }
        return $retVal;
    }    
    
    /**
     * @return string|bool Max. Current count of player online on the server
     */
    public function getModType() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('modinfo', $this->serverInfo['decodedResponse']) && 
                array_key_exists('type', $this->serverInfo['decodedResponse']['modinfo'])) {            
                $retVal = $this->serverInfo['decodedResponse']['modinfo']['type'];
            }
        }
        return $retVal;
    }     
    

    /**
     * @return array|bool Mods the server is using with its id ('modid') 
     *                    and version ('version') (currently only Forge?!)
     */
    public function getModList() {
        $retVal = false;
        if ($this->isOnline()) {
            if (array_key_exists('modinfo', $this->serverInfo['decodedResponse']) && 
                array_key_exists('modList', $this->serverInfo['decodedResponse']['modinfo'])) {            
                $retVal = $this->serverInfo['decodedResponse']['modinfo']['modList'];
            }
        }
        return $retVal;
    }       
   
}
