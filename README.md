# PHP-MinecraftServerInfo #
---


## Description
PHP library to query information from a Minecraft server for Minecraft servers 1.7 and higher.


## Features

<ul>
<li>Easy to use object orientated PHP class</li>
<li>Supports newest Minecraft server version 1.7 and higher</li>
<li><strong>NO</strong> requirement for plugin, RCON or to enable Query on the server, it works straight out of the box on every Minecraft server.</li>
<li>Retrieves online player names as well</li>
</ul>



## How to use it

Simply create an object of the class MinecraftServerInfo and pass the hostname and optionally the port. Afterwards call the *Query* method which returns true if the server is online or false in case the server is offline or an error has happended


## Requirements

<ul>
<li>PHP 5.4.0</li>
<li>Socket stream must be allowed (stream_socket_client, fwrite, fread, fclose)</li>
</ul>


