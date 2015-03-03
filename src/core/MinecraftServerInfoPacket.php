<?php

/**
 * MinecraftServerInfoPacket
 *
 * @author Patrick Weiss <info@tekgator.com> http://tekgator.com
 * @copyright (c) 2015, Patrick Weiss
 * 
 */
abstract class MinecraftServerInfoPacket {
    
    public static function packVarInt($int) {
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
    
    public static function packData($data) {
        return self::packVarInt(strlen($data)) . $data;
    }  
    
    public static function unpackVarInt($fp, &$response=null) {
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
}
