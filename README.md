# PHP-MinecraftServerInfo #


## Description
PHP library to query information from a Minecraft server for Minecraft servers 1.7 and higher.


## Features

- Easy to use object orientated PHP class
- Supports newest Minecraft server version 1.7 and higher
- **NO** requirement for plugin, RCON or to enable Query on the server, it works straight out of the box on every Minecraft server.
- Retrieves online player names (limited)
- SRV record resolving: No need to know the exact port of the Minecraft server if a SRV record is configured



## How to use it

Simply create an object of the class MinecraftServerInfoQueryTcp and pass the hostname and optionally the port. Via the magic methods server properties can be retrieved.

```php
<?php
	require_once 'MinecraftServerInfoQueryTcp.php';

	$mcInfo = new MinecraftServerInfoQueryTcp('hostname');

	if ($mcInfo->Online === true) {
		echo 'Version: ' . $mcInfo->Version;
		echo 'Version: ' . $mcInfo->Description;
		...
		...
	}
```

    
## Properties to retrieve server information

Magic get properties are **not** case sensitive. If a property is not available *FALSE* is retuned. 

- **Online**: Indicates if the server is reachable
- **Response**: The decoded JSON stream returned from the Minecraft server 
- **Error**: In case a error occured this property maybe filled
- **HostName**: Hostname of the server (maybe different from input due to SRV record resolving)
- **IPAdress**: IP adress of the server
- **Port**: Port of the server (maybe gathered from the SRV record)
- **Latency**: Ping to the server im ms
- **FavIcon**: Server icon as base64 string which can be assigned to the src property of the HTML img tag
- **Version**: Minecraft server version
- **Protocol**: Protocol version the Minecraft server is using for communication
- **Description**: Message of the day
- **PlayerMax**: Count of maximum possible players on the server
- **PlayerOnline**: Count of current players on the server
- **Players**: Array of players on the server with name and UUID (sample only is returned from the server, most likly around 10 - 15 players) 
- **ModInfo**: In case of a Forge server a mod list maybe returned


## Requirements

- PHP 5.4.0
- Socket stream must be allowed (stream_socket_client, fwrite, fread, fclose)


