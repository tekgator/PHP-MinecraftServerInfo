<?php

require_once 'MinecraftServerInfo.php';

$mcInfo = new MinecraftServerInfo('MC.FunServerGer.de');
$mcInfo->Query();

if ($mcInfo->Get('favicon') !== false) {
    echo '<div><img src="' . $mcInfo->Get('favicon') . '"</div>';
}

if ($mcInfo->Get('lastError') !== false) {
    echo '<div>Last error: '        . $mcInfo->Get('lastError')         . '</div>';
}

echo '<div>Hostname: '          . $mcInfo->Get('hostName')          . '</div>';
echo '<div>IP Adress: '         . $mcInfo->Get('ipAdress')          . '</div>';
echo '<div>Port: '              . $mcInfo->Get('port')              . '</div>';
echo '<div>Ping: '              . $mcInfo->Get('ping')              . '</div>';
echo '<div>Online: '            . $mcInfo->Get('online')            . '</div>';

if ($mcInfo->Get('online') === true) {
    
    echo '<div>Software: '          . $mcInfo->Get('serversoftware')    . '</div>';
    echo '<div>Version: '           . $mcInfo->Get('version')           . '</div>';
    echo '<div>Protocol: '          . $mcInfo->Get('protocolversion')   . '</div>';
    echo '<div>MOTD: '              . $mcInfo->Get('motd')              . '</div>';
    echo '<div>Max. players: '      . $mcInfo->Get('playermax')         . '</div>';
    echo '<div>Online players: '    . $mcInfo->Get('playeronline')      . '</div>';

    if (is_array($mcInfo->Get('playerids'))) {
        echo '<div><ul>';
        foreach ($mcInfo->Get('playerids') as $player) {
            echo '<li>' . $player['name'] . ' (' . $player['id'] . ')</li>';
        }
        echo'</ul></div>';
    }

    if ($mcInfo->Get('modtype') != false) {
        echo '<div>Mod type: '    . $mcInfo->Get('modtype')       . '</div>';

        if (is_array($mcInfo->Get('modlist'))) {
            echo '<div><ul>';
            foreach ($mcInfo->Get('modlist') as $mod) {
                echo '<li>' . $mod['modid'] . ' (' . $mod['version'] . ')</li>';
            }
            echo'</ul></div>';
        }
    }
    
    echo '<br /><br /><br />';
    
    echo '<div><pre>';
    echo print_r(json_decode($mcInfo->Get('json'), true));
    echo '</pre></div>';

    
}