<?php

require_once 'core/MinecraftServerInfoConfig.php';
require_once 'core/MinecraftServerInfoDns.php';
require_once 'core/MinecraftServerInfoPacket.php';

/**
 * MinecraftServerInfo
 * Class to query information from a Minecraft server via TCP
 * 
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * @link http://wiki.vg/Server_List_Ping Protocol description
 * @version 1.1
 * 
 */
class MinecraftServerInfo {
    
    private $mcDns = false;
    private $mcConn = false;
    private $connTime = 0;
    
    private $serverInfo = array();
    

    /**
     * @param string $hostname Description
     * 
     */
    public function __construct($hostname = '') {
        $this->mcDns = new MinecraftServerInfoDns($hostname);
    }
    
    public function __destruct() {
        $this->disconnectSocket();
    }
    
    /**
     * Connect to the minecraft server passed within 
     * the constructor and query the data
     * 
     * @return bool returns true if server is online or false
     *              if offline or error occured (check last error)
     * 
     */
    public function Query() {
        $this->serverInfo = array();
        $this->serverInfo['online'] = false;
        
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
        
        return $this->serverInfo['online'];
    }
    
    /**
     * all properties are data type as defined below
     * if existing, otherwise bool false is returned
     * (property names are NOT case sensitive, so write them as you like)
     * 
     * @param string    'hostName'        Server DNS Adress
     * @param string    'ipAdress'        Server IP Adress
     * @param int       'port'            Server Port
     * @param int       'ping'            Latency time to server in ms
     * @param bool      'online'          Server is online (true) or offline (false)
     * @param string    'serversoftware'  Serversoftware in use (e.g. Vanilla, Craftbukkit, ForgeMod, etc.)
     * @param string    'version'         Server version (e.g. 1.8)
     * @param int       'protocolversion' Server protocol version
     * @param string    'motd'            Message of the day / Description (careful can contain special character -> filter)
     * @param int       'playermax'       Max. count of player possible on the server
     * @param int       'playeronline'    Current count of player online on the server
     * @param array     'playerids'       Array of online player names ('name') and UUID ('id') NOTE: returns a portion of online players only
     * @param string    'favicon'         Base64 string of the server icon (can be used within src attribute of img tag)
     * @param string    'modtype'         Type of mods the server is implementing (currently only FML?!)
     * @param array     'modlist'         Mods the server is using with its id ('modid') and version ('version') (currently only Forge?!)
     * @param string    'json'            The undecode JSON string receive from the Minecraft server
     * @param string    'lastError'       Contains the last error occured during DNS resolving or server query
     * 
     */
    public function Get($name) {
        $accessKey = strtolower($name);
        $retVal = false;
        
        if (!empty($accessKey) && array_key_exists($accessKey, $this->serverInfo)) {
            $retVal = $this->serverInfo[$accessKey];
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
            if (array_key_exists('version', $decodedJson)) {            
                /* if the version key exists the server is definitly online */
                $this->serverInfo['online'] = true; 
                
                if (array_key_exists('name', $decodedJson['version'])) {            
                    $this->extractVersion($decodedJson['version']['name'], 
                                          $this->serverInfo['serversoftware'], 
                                          $this->serverInfo['version']);
                }
                
                if (array_key_exists('protocol', $decodedJson['version'])) {            
                    $this->serverInfo['protocolversion'] = $decodedJson['version']['protocol'];
                }
            }
            
            if (array_key_exists('description', $decodedJson)) {
                $this->serverInfo['motd'] = $decodedJson['description'];
            }
            
            if (array_key_exists('players', $decodedJson)) {
                if (array_key_exists('max', $decodedJson['players'])) {            
                    $this->serverInfo['playermax'] = $decodedJson['players']['max']; 
                }
                
                if (array_key_exists('online', $decodedJson['players'])) {            
                    $this->serverInfo['playeronline'] = $decodedJson['players']['online']; 
                }
                
                if (array_key_exists('sample', $decodedJson['players'])) {            
                    $this->serverInfo['playerids'] = $decodedJson['players']['sample'];
                }
                
            }
            
            if (array_key_exists('favicon', $decodedJson)) {
                $this->serverInfo['favicon'] = $decodedJson['favicon'];
            }

            if (array_key_exists('modinfo', $decodedJson)) {
                if (array_key_exists('type', $decodedJson['modinfo'])) {
                    $this->serverInfo['modtype'] = $decodedJson['modinfo']['type'];
                    
                    if ($this->serverInfo['modtype'] == 'FML') {
                        $this->serverInfo['serversoftware'] = 'ForgeMod';
                    }
                }
                
                if (array_key_exists('modList', $decodedJson['modinfo'])) {
                    $this->serverInfo['modlist'] = $decodedJson['modinfo']['modList'];
                }
            }
        } else {
            $this->serverInfo['lasterror'] = 'JSON stream cannot be decoded.' .
                                             ' /errorNo='  . json_last_error() .
                                             ' /errorMsg=' . json_last_error_msg();
        }
        
    }
        
    private function extractVersion($versionStr, &$serverSoftware, &$version) {
        $serverSoftware = 'Vanilla';
        $version = $versionStr;
        
        for ($i = 0; $i < strlen($versionStr); $i++) {
            if (is_numeric($versionStr[$i])) {
                if ($i == 0) {
                    /* apparently the string contains only the version
                     * so it's most likly a vanilla server, anyways later on
                     * is a determination if the mod tree 
                     * exists so it may a ForgeMod server */
                    $version = $versionStr;
                } else {
                    $serverSoftware = trim(substr($versionStr, 0, $i));
                    $version = trim(substr($versionStr, $i));
                }
                break;
            }
        }
    }
    
}
