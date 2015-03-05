<?php

require_once 'MinecraftServerInfoQueryTcp.php';

//$mcInfo = new MinecraftServerInfoQueryTcp('MC.FunServerGer.de'); // 1.5.2 server
//$mcInfo = new MinecraftServerInfoQueryTcp('s.hexxit.de:25585'); // 1.5.2 server
//$mcInfo = new MinecraftServerInfoQueryTcp('kadcon.de'); // Bungecoord Server (Spigot)
//$mcInfo = new MinecraftServerInfoQueryTcp('78.143.2.54'); // 1.7.10 spigot
//$mcInfo = new MinecraftServerInfoQueryTcp('85.190.132.83'); // 1.8 spigot
$mcInfo = new MinecraftServerInfoQueryTcp('mc.craftymynes.com'); // 1.8.3 vanilla
//$mcInfo = new MinecraftServerInfoQueryTcp('play.skylarkpvp.net'); // 1.7.10 vanilla



if ($mcInfo->FavIcon) {
    echo '<div><img src="' . $mcInfo->FavIcon . '"</div>';
}

if ($mcInfo->Error !== false) {
    echo '<div>Last error: '        . $mcInfo->Error           . '</div>';
}

echo '<div>Hostname: '          . $mcInfo->HostName            . '</div>';
echo '<div>IP Adress: '         . $mcInfo->IPAdress            . '</div>';
echo '<div>Port: '              . $mcInfo->Port                . '</div>';
echo '<div>Latency: '           . $mcInfo->Latency             . '</div>';
echo '<div>Online: '            . $mcInfo->Online              . '</div>';

if ($mcInfo->Online === true) {
    
    echo '<div>Version: '           . $mcInfo->Version             . '</div>';
    echo '<div>Protocol: '          . $mcInfo->Protocol            . '</div>';
    echo '<div>MOTD: '              . $mcInfo->Description         . '</div>';
    echo '<div>Max. players: '      . $mcInfo->PlayerMax           . '</div>';
    echo '<div>Online players: '    . $mcInfo->PlayerOnline        . '</div>';

    if (is_array($mcInfo->Players)) {
        echo '<div><ul>';

        foreach ($mcInfo->Players as $player) {
            echo '<li>' . $player['name'] . ' (' . $player['id'] . ')</li>';
        }

        echo'</ul></div>';
    }

    if ($mcInfo->ModInfo != false && array_key_exists('type', $mcInfo->ModInfo)) {
        echo '<div>Mod type: '    . $mcInfo->ModInfo['type']    . '</div>';

        if (is_array($mcInfo->ModInfo['modList'])) {
            echo '<div><ul>';
            foreach ($mcInfo->ModInfo['modList'] as $mod) {
                echo '<li>' . $mod['modid'] . ' (' . $mod['version'] . ')</li>';
            }
            echo'</ul></div>';
        }
    }
    
    echo '<br /><br /><br />';
    
    echo '<div><h1>Decoded JSON array from server response</h1></div>';
    echo '<div><pre>';
    echo print_r($mcInfo->Response, true);
    echo '</pre></div>';

}