<?php

require_once 'core/MinecraftServerInfoConfig.php';
require_once 'core/MinecraftServerInfoDns.php';
require_once 'core/MinecraftServerInfoPacket.php';

/**
 * Description of MinecraftServerInfo - TODO
 *
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * 
 */
class MinecraftServerInfo {
    
    private $mcDns = false;
    private $mcConn = false;
    private $connTime = 0;
    
    private $serverInfo = array();
    
    
    public function __construct($hostname = '') {
        $this->mcDns = new MinecraftServerInfoDns($hostname);
    }
    
    public function __destruct() {
        $this->disconnectSocket();
    }
    
    /*
     * @see http://wiki.vg/Server_List_Ping
     */
    public function Query() {
        $this->serverInfo = array();
        
        do {
            /* 1.) prepare handshake message (do before hand to not 
             *     increase latency time measurement to server)
             */
            $handshakePacket = MinecraftServerInfoPacket::buildHandshakeMessage(
                    $this->mcDns->Get('ipadress'),
                    $this->mcDns->Get('port'),
                    MinecraftServerInfoConfig::MINECRAFT_PROTOCOL_VERSION);
            
            /* 2.) prepare status request message */
            $statusRequestPacket = MinecraftServerInfoPacket::buildStatusRequestMessage();
            

            /* 3.) connect to minecraft server */
            if (!$this->connectSocket()) { break; }
            
            /* 4.) send handshake and status request message */
            if (!$this->writeSocket($handshakePacket) ||
                !$this->writeSocket($statusRequestPacket)) { break; }
            
            /* 5.) evaluate response and ping */
            if (!$this->unpackResponse()) { break; }
                
            /* 6.) decode JSON string into server info array */
            if (!$this->decodeJson()) { break; }
            
            break; /* always exit fake loop */
        } while(true);
        
        $this->disconnectSocket();
    }
    
    /*
     * all properties are data type as defined below 
     * if existing, otherwise bool false is returned
     * 
     * @property-read string    hostName    Minecraft Server DNS Adress
     * @property-read string    ipAdress    Minecraft Server IP Adress
     * @property-read int       port        Minecraft Server Port
     * @property-read int       ping        Latency time to Minecraft server in ms
     * 
     * 
     * 
     * 
     * 
     * @property-read string    json        The undecode JSON string receive from the Minecraft server
     * @property-read string    lastError   Contains the last error occured during DNS resolving or server query
     * 
     */
    public function Get($name) {
        $accessKey = strtolower($name);
        $retVal = false;
        
        if (!empty($accessKey) && array_key_exists($accessKey, $this->serverInfo)) {
            $retVal = $this->connInfo[$accessKey];
        } else {
            if ($accessKey == 'hostname' ||
                $accessKey == 'ipadress' ||
                $accessKey == 'port') {
                $retVal = $this->mcDns->Get($name);
            }
        }
        
        return $retVal;
    }
    
    
    private function connectSocket() {
        
        if ($this->mcConn === false) {
            $this->connTime = microtime(true);
            $this->mcConn = stream_socket_client('tcp://' . $this->mcDns->Get('ipadress') . ':' . $this->mcDns->Get('port'), 
                                                 $errNo, 
                                                 $errMsg, 
                                                 MinecraftServerInfoConfig::TCP_TIMEOUT_SECONDS);
            
            if ($this->mcConn) {
                stream_set_timeout($this->mcConn, MinecraftServerInfoConfig::TCP_TIMEOUT_SECONDS);
            } else {
                $this->serverInfo['lasterror'] = 'Error connecting to client' .
                                                 ' /hostName=' . $this->mcDns->Get('hostname') .
                                                 ' /ipAdress=' . $this->mcDns->Get('ipadress') .
                                                 ' /port='     . $this->mcDns->Get('port') .
                                                 ' /errorNo='  . $errNo .
                                                 ' /errorMsg=' . $errMsg;
                $this->disconnect();                
            }
        }
        
        return ($this->mcConn != false);
    }
    
    private function writeSocket($data) {
        return fwrite($this->mcConn, $data);
    }
    
    private function disconnectSocket() {
        if ($this->mcConn != false) {
            fclose($this->mcConn);
            $this->mcConn = false;
        }
        
        $this->connTime = 0;
    }
    
    
    private function unpackResponse() {
        $response = '';
        
        do {
            /* first part of respone is the length of the packet */
            if (MinecraftServerInfoPacket::unpackVarInt($this->mcConn, $response) === false) {
                $this->serverInfo['lasterror'] = 'Invalid response to handshake and status messagereceived from Minecraft server';
                break;
            }
            
            /* if first part of answer has been received we can set the latency */
            $this->serverInfo['ping'] = round((microtime(true) - $this->connTime) *1000);

            /* second part of respone is the packet ID */
            if (MinecraftServerInfoPacket::unpackVarInt($this->mcConn, $response) === false) {
                $this->serverInfo['lasterror'] = 'Invalid packet ID received from Minecraft server';
                break;
            }

            /* third part contains the length of the following JSON string */
            $jsonLength = MinecraftServerInfoPacket::unpackVarInt($this->mcConn, $response);
            if ($jsonLength === false || $jsonLength === 0) {
                $this->serverInfo['lasterror'] = 'Invalid JSON stream received from Minecraft server';
                break;
            }
            
            $jsonStr = '';
            $readLen = 0;
            do {
                /* reading length has to be determined, because if we read more than 
                 * available data we run into timeout or until server send the next data stream */
                $readLen = min(2048, $jsonLength - strlen($jsonStr));
                
                /* exit loop if no more data has been 
                 * found or the whole JSON string has been read */
                if ($readLen <= 0 || feof($this->mcConn)) { break; }
                
                $jsonStr .= fread($this->mcConn, $readLen);
            } while(true);
            
            $this->serverInfo['json'] = $jsonStr;
            
            break; /* always exit fake loop */
        } while(true);
        
        return array_key_exists('json', $this->serverInfo) && !empty($this->serverInfo['json']);
    }
    
    private function decodeJson() {
    
        $decodedJson = json_decode($this->serverInfo['json'], true);
        
        if (is_string($decodedJson)) {
            /* server returned simple string which is an error meesage (e.g. server is starting up) */
            $this->serverInfo['lasterror'] = $decodedJson;
        } elseif (is_array($decodedJson)) {

            
            
            
        } else {
            $this->serverInfo['lasterror'] = 'JSON stream cannot be decoded.' .
                                             ' /errorNo='  . json_last_error() .
                                             ' /errorMsg=' . json_last_error_msg();
        }
        
    }
        
    
}
