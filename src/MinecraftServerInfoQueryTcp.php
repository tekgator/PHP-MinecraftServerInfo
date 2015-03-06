<?php

require_once 'MinecraftServerInfoConfig.php';
require_once 'MinecraftServerInfoDns.php';

/**
 * MinecraftServerInfoQueryTcp
 * Class to query information from a Minecraft server via TCP ping
 * 
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * @version 1.4
 * @link http://wiki.vg/Server_List_Ping Protocol description
 * 
 */
class MinecraftServerInfoQueryTcp {
   
    private $serverInfo;

    public function __construct($hostname = '', $port = 0) {
        $mcDns = new MinecraftServerInfoDns($hostname, $port);
        
        $this->serverInfo = ['hostname'     => $mcDns->getHostName(),
                             'ipadress'     => $mcDns->getIPAdress(),
                             'port'         => $mcDns->getPort()];
        
        $this->Refresh();
    }

    public function Refresh() {
       
        do {
            /* 1. reset serverInfo array, but keep basic server information */
            $this->serverInfo = array_intersect_key($this->serverInfo, array('hostname' => '', 
                                                                             'ipadress' => '', 
                                                                             'port'     => ''));

            /* 2.) prepare handshake message (do before hand to not 
             *     increase latency time measurement to server) */
            $handshakePacket = self::packData(
                    chr(0) .
                    self::packVarInt(MinecraftServerInfoConfig::MINECRAFT_PROTOCOL_VERSION) .
                    self::packData($this->serverInfo['hostname']) .
                    pack('n', $this->serverInfo['port']) .
                    self::packVarInt(1));
            
            /* 3.) prepare status request message */
            $statusRequestPacket = self::packData(chr(0));
            
            /* 4.) connect to minecraft server */
            $connectTime = microtime(true);
            $fp = stream_socket_client('tcp://' . $this->serverInfo['hostname'] . ':' . $this->serverInfo['port'], 
                                       $errNo, 
                                       $errMsg, 
                                       MinecraftServerInfoConfig::CONNECTION_TIMEOUT_SECONDS);

            if ($fp) {
                stream_set_blocking($fp, 1);
                stream_set_timeout($fp, MinecraftServerInfoConfig::CONNECTION_TIMEOUT_SECONDS);
            } else {
                $this->serverInfo['error'] = 'TCP connection failed, server maybe offline.' .
                            ' /hostName='   . $this->serverInfo['hostname'] .
                            ' /ipAdress='   . $this->serverInfo['ipadress'] .
                            ' /port='       . $this->serverInfo['port'] .
                            ' /errNo='      . $errNo .
                            ' /errMsg='     . $errMsg;
                break;
            }
            
            /* 5.) send handshake and status request message */
            if (!fwrite($fp, $handshakePacket) ||
                !fwrite($fp, $statusRequestPacket)) { break; }
            
            /* 6.) evaluate response and ping */
            if (!$this->unpackResponse($fp, $connectTime)) { break; }

            /* 7.) if a valid response has been received the server is online */
            $this->serverInfo['online'] = true;
            
            break; /* always exit fake loop */
        } while(true);
        
        if ($fp) {
            fclose($fp);
        }
    }


    public function __get($name) {
        $name  = strtolower($name);
        $value = false;

        switch ($name) {
            case 'version':
                if(array_key_exists('version', $this->serverInfo['response']) &&
                   array_key_exists('name', $this->serverInfo['response']['version'])) {
                    $value = $this->serverInfo['response']['version']['name'];
                }
                break;

            case 'protocol':
                if(array_key_exists('version', $this->serverInfo['response']) &&
                   array_key_exists('protocol', $this->serverInfo['response']['version'])) {
                    $value = $this->serverInfo['response']['version']['protocol'];
                }
                break;

            case 'playermax':
                if(array_key_exists('players', $this->serverInfo['response']) &&
                   array_key_exists('max', $this->serverInfo['response']['players'])) {
                    $value = $this->serverInfo['response']['players']['max'];
                }
                break;

            case 'playeronline':
                if(array_key_exists('players', $this->serverInfo['response']) &&
                   array_key_exists('online', $this->serverInfo['response']['players'])) {
                    $value = $this->serverInfo['response']['players']['online'];
                }
                break;

            case 'players':
                if(array_key_exists('players', $this->serverInfo['response']) &&
                   array_key_exists('sample', $this->serverInfo['response']['players'])) {
                    $value = $this->serverInfo['response']['players']['sample'];
                }
                break;

            default:
                if (array_key_exists($name, $this->serverInfo)) {
                    $value = $this->serverInfo[$name];
                } elseif(array_key_exists($name, $this->serverInfo['response'])) {
                    $value = $this->serverInfo['response'][$name];
                }
                break;
        }
        
        return $value;
    }
    

    private function packData($data) {
        return $this->packVarInt(strlen($data)) . $data;
    }      

    private function packVarInt($int) {
        $varInt = '';
        while (true) {
            if (($int & 0xFFFFFF80) === 0) {
                $varInt .= chr($int);
                return $varInt;
            }
            $varInt .= chr($int & 0x7F | 0x80);
            $int >>= 7;
        }
    }    

    private function unpackVarInt($fp, &$response=null) {
        $retVal = 0;
        $pos = 0;
        while (true) {
            $part = fread($fp, 1);
            if ($response !== null) {
                $response .= $part;
            }
            $byte = ord($part);
            $retVal |= ($byte & 0x7F) << $pos++ * 7;
            if ($pos > 5) {
                /* error VarInt too big */
                $retVal = false;
                break;
            }
            if (($byte & 0x80) !== 128) {
                break;
            }
        }
        return $retVal;
    }
    
    private function unpackResponse($fp, $connectTime) {
        $response = '';
        
        do {
            /* first part of respone is the length of the packet */
            if (self::unpackVarInt($fp, $response) === false) {
                $this->serverInfo['error'] = 'Invalid response to handshake and status message received from server';
                break;
            }
            
            /* if first part of answer has been received we can set the latency */
            $this->serverInfo['latency'] = round((microtime(true) - $connectTime) * 1000);

            /* second part of respone is the packet ID */
            if (self::unpackVarInt($fp, $response) === false) {
                $this->serverInfo['error'] = 'Invalid packet ID received from Minecraft server';
                break;
            }

            /* third part contains the length of the following JSON string */
            $jsonLength = self::unpackVarInt($fp, $response);
            if ($jsonLength === false || $jsonLength === 0) {
                $this->serverInfo['error'] = 'Invalid JSON stream received from server';
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
                if ($readLen <= 0 || feof($fp)) { break; }
                
                $part = fread($fp, $readLen);
                $jsonStr .= $part;
            } while($part != false);
            
            if ($part == false) {
                $this->serverInfo['error'] = 'Invalid JSON stream received from server';
                break;
            }
            
            /* decode received string into an array */
            $this->serverInfo['response'] = json_decode($jsonStr, true);

            if (is_string($this->serverInfo['response'])) {
                $this->serverInfo['error'] = $this->serverInfo['response'];
            } elseif (!is_array($this->serverInfo['response'])) {
                $this->serverInfo['error'] = 'Error occured while decoding server response.' .
                        ' /errNo=' . json_last_error() .
                        ' /errMsg=' . json_last_error_msg();
            }
            
            break; /* always exit fake loop */
        } while(true);
        
        return !array_key_exists('error', $this->serverInfo);
    }    
    
}
