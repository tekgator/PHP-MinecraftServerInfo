<?php

require_once 'core/MinecraftServerInfoConfig.php';
require_once 'core/MinecraftServerInfoDns.php';
require_once 'core/MinecraftServerInfoPacket.php';


/**
 * MinecraftServerInfoTcpPing
 *
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * @link http://wiki.vg/Server_List_Ping Protocol description
 * 
 */
class MinecraftServerInfoTcpPing {
   
    private $isOnline = false;
    private $latency = 0;
    private $repsonse = '';
    private $lastError = '';
    

    public function __construct($mcDns) {
        $this->refresh($mcDns);
    }
    
    /**
     * Returns whether the minecraft server is on/offline
     * @return bool
     */
    public function isOnline() {
        return $this->isOnline;
    }

    /**
     * Returns latency to minecraft server
     * @return int
     */
    public function getLatency() {
        return $this->latency;
    }

    /**
     * Returns the undecoded response of the minecraft server
     * @return string
     */    
    public function getResponse() {
        return $this->repsonse;
    }

    /**
     * In case the server is offline the last thrown error can be checked here
     * @return string
     */    
    public function getLastError() {
        return $this->lastError;
    }
    
    public function refresh($mcDns) {
        $this->isOnline = false;
        $this->latency = 0;
        $this->repsonse = '';
        $this->lastError = '';
        
        do {
            /* 1.) check that a valid instance of 
             * MinecraftServerInfoDns has been passed */
            if (!$mcDns instanceof MinecraftServerInfoDns) {
                $this->lastError = 'Invalid DNS object passed!';
                break;
            }
            
            /* 2.) prepare handshake message (do before hand to not 
             *     increase latency time measurement to server) */
            $handshakePacket = MinecraftServerInfoPacket::buildHandshakeMessage(
                    $mcDns->getIPAdress(),
                    $mcDns->getPort(),
                    MinecraftServerInfoConfig::MINECRAFT_PROTOCOL_VERSION);
            
            /* 3.) prepare status request message */
            $statusRequestPacket = MinecraftServerInfoPacket::buildStatusRequestMessage();
            
            /* 4.) connect to minecraft server */
            $connectTime = microtime(true);
            $fp = stream_socket_client('tcp://' . $mcDns->getIPAdress() . ':' . $mcDns->getPort(), 
                                       $errNo, 
                                       $errMsg, 
                                       MinecraftServerInfoConfig::TCP_TIMEOUT_SECONDS);

            if ($fp) {
                stream_set_timeout($fp, MinecraftServerInfoConfig::TCP_TIMEOUT_SECONDS);
            } else {
                $this->lastError = 'TCP connection failed, server maybe offline.' .
                        ' /hostName='   . $mcDns->getHostName() .
                        ' /ipAdress='   . $mcDns->getIPAdress() .
                        ' /port='       . $mcDns->getPort() .
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
            $this->isOnline = true;
            
            break; /* always exit fake loop */
        } while(true);
        
        if ($fp) {
            fclose($fp);
        }
    }
    
    private function unpackResponse($fp, $connectTime) {
        $response = '';
        
        do {
            /* first part of respone is the length of the packet */
            if (MinecraftServerInfoPacket::unpackVarInt($fp, $response) === false) {
                $this->lastError = 'Invalid response to handshake and status message received from server';
                break;
            }
            
            /* if first part of answer has been received we can set the latency */
            $this->latency = round((microtime(true) - $connectTime) * 1000);

            /* second part of respone is the packet ID */
            if (MinecraftServerInfoPacket::unpackVarInt($fp, $response) === false) {
                $this->lastError = 'Invalid packet ID received from Minecraft server';
                break;
            }

            /* third part contains the length of the following JSON string */
            $jsonLength = MinecraftServerInfoPacket::unpackVarInt($fp, $response);
            if ($jsonLength === false || $jsonLength === 0) {
                $this->lastError = 'Invalid JSON stream received from server';
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
                $this->lastError = 'Invalid JSON stream received from server';
                break;
            }
            
            $this->repsonse = $jsonStr;
            
            break; /* always exit fake loop */
        } while(true);
        
        return !empty($this->repsonse);
    }    
    
}
