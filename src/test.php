<?php

require_once 'MinecraftServerInfo.php';

//$mcInfo = new MinecraftServerInfo('MC.FunServerGer.de'); // 1.5.2 server
//$mcInfo = new MinecraftServerInfo('s.hexxit.de:25585'); // 1.5.2 server
//$mcInfo = new MinecraftServerInfo('kadcon.de'); // Bungecoord Server (Spigot)
//$mcInfo = new MinecraftServerInfo('78.143.2.54'); // 1.7.10 spigot
//$mcInfo = new MinecraftServerInfo('85.190.132.83'); // 1.8 spigot
$mcInfo = new MinecraftServerInfo('mc.craftymynes.com'); // 1.8.3 vanilla
//$mcInfo = new MinecraftServerInfo('play.skylarkpvp.net'); // 1.7.10 vanilla


$mcInfo->Query();

if ($mcInfo->getFavIcon()) {
    echo '<div><img src="' . $mcInfo->getFavIcon() . '"</div>';
}

if ($mcInfo->getLastError() !== false) {
    echo '<div>Last error: '        . $mcInfo->getLastError()           . '</div>';
}

echo '<div>Hostname: '          . $mcInfo->getHostName()            . '</div>';
echo '<div>IP Adress: '         . $mcInfo->getIPAdress()            . '</div>';
echo '<div>Port: '              . $mcInfo->getPort()                . '</div>';
echo '<div>Latency: '           . $mcInfo->getLatency()             . '</div>';
echo '<div>Online: '            . $mcInfo->isOnline()               . '</div>';

if ($mcInfo->isOnline() === true) {
    
    echo '<div>Version: '           . $mcInfo->getVersion()             . '</div>';
    echo '<div>Protocol: '          . $mcInfo->getProtocolVersion()     . '</div>';
    echo '<div>MOTD: '              . $mcInfo->getMotd()                . '</div>';
    echo '<div>Max. players: '      . $mcInfo->getMaxPlayerCount()      . '</div>';
    echo '<div>Online players: '    . $mcInfo->getOnlinePlayerCount()   . '</div>';

    if (is_array($mcInfo->getOnlinePlayers())) {
        echo '<div><ul>';
/*
        foreach ($mcInfo->getOnlinePlayers(true) as $player) {
            echo '<li>' . $player['name'] . ' (' . $player['id'] . ')</li>';
        }
*/ 
        foreach ($mcInfo->getOnlinePlayers() as $player) {
            echo '<li>' . $player . '</li>';
        }

        echo'</ul></div>';
    }

    if ($mcInfo->getModType() != false) {
        echo '<div>Mod type: '    . $mcInfo->getModType()              . '</div>';

        if (is_array($mcInfo->getModList())) {
            echo '<div><ul>';
            foreach ($mcInfo->getModList() as $mod) {
                echo '<li>' . $mod['modid'] . ' (' . $mod['version'] . ')</li>';
            }
            echo'</ul></div>';
        }
    }
    
    echo '<br /><br /><br />';
    
    echo '<div><h1>Decoded JSON array from server response</h1></div>';
    echo '<div><pre>';
    echo print_r($mcInfo->getResponse(), true);
    echo '</pre></div>';

    echo '<br /><br /><br />';    
    
    echo '<div><h1>Raw data from server</h1></div>';
    echo '<div>' . $mcInfo->getResponse(false) . '</div>';

    
    
}