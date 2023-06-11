# Php Telegram RSS Audio Poster

## Overview
A PHP application to process multiple RSS feeds containing video & audio and post them to Telegram as an MP3 audio file.

Supports video content from Odysee & YouTube (with the use of youtube-dl) and audio content from podcast sources such as Podomatic, Zencast & itunes.

## feeds.json
A JSON formatted file containing an array of the RSS feeds to be processed e.g.

```bash
[
 {
    "url": "https://odysee.com/$/rss/@Odysee:8",
    "match": "/.*/i",
    "datelimit": "2021-01-20",
    "performer": "Odysee Example",
    "desc": "<b>[title]</b>\r\n  - [longdate]\r\n\r\n <a href='#'>\uD83C\uDF7A</a>  |  <a href='[link]'>Odysee</a>  |  <a href='#'>Telegram Audio \uD83D\uDD0A</a>",
    "icon": "icon/example.png",
    "active": true
 }
]  
```

### Feed Attributes

|name|desc|example|
|--|--|--|
|url|URL to an RSS feed|https://odysee.com/$/rss/@Odysee:8|
|type|special processing for audio (mp3 enclosure) and youtube-dl|none,youtube,audio|
|match|valid regex to match the RSS item name. use .* to match everything|/.*/i|
|datelimit|do not process items older than|2021-01-20|
|performer|appears in the posted message|Odysee Example|
|bitchute-channelid|only required for use with bitchute urls|HyEIxBTnCeDy|
|desc|html formatted description used as the Telegram message body. can include templates for [title] and [url]|&lt;b&gt;[title]&lt;/b&gt;|
|icon|link to a png or jpg shown in the Telegram message|icon/example.png|
|chatid|optional override to send this feed to a different telegram channel|123456789|
|active|boolean whether to process this feed|false|
|compression|optional valid ffmpeg compression string to apply to output audio|-qscale:a 5 -ar 22050|

## process-rss.php
The PHP script that processes `feeds.json` and posts the resulting audio to Telegram. Optionally called by a controlling bash script `start.sh` to prevent multiple instances of the script running concurrently. 
The script utilises the following working directories:

 - `./guid/` - stores a marker for every processed RSS item to prevent duplicates.
 - `./icon/` - stores jpg/png image file used as an icon for the Telegram message.
 - `./tmp/` - used as storage area to download the media to prior to running ffmpeg.
 - `./out/` - stores a copy of every generated audio MP3 file. These can be deleted after the Telegram message has been posted.

## telegram-bot-api
It is desirable to run a local instance of the Telegram bot API to avoid restrictions placed on users of the official bot API (such as a 50MB file size limit). Simple instructions are included below on where to get the code & how to compile and run it.

### Create a Telegram Bot

 1. Using these instructions https://core.telegram.org/bots create a new
    Telegram bot using @BotFather. Keep a copy of the API Token provided by @BotFather
 2. Register an end-point (webhook) for the bot. This step is described well here https://nordicapis.com/how-to-build-your-first-telegram-bot-using-php-in-under-30-minutes/. Telegram will send data to this end point when the bot interacts. You will use this to determine the channel ID(s) the bot connects to. For example, you could register the end-point http://your-server-ip/botreceive.php and host a simple PHP file there which outputs all Telegram data in to the PHP error log. e.g.
```php
<?php
$update = json_decode(file_get_contents("php://input"), TRUE);
error_log(print_r($update, true));
```
 3. Now invite the bot in to your Telegram channel and observe the chatter sent to your end point. You will see there the Channel ID that looks something like: 
```php
-1001411318385
```
 4. Once you have obtained the Channel ID(s), you will no longer require the end-point.

### Run a Local Instance of telegram-bot-api
If the files you wish to post are always less than 50MB, then you can use the https://api.telegram.org API. Otherwise, you can download the telegram-bot-api from https://github.com/tdlib/telegram-bot-api compile it, and run it in a screen e.g. 

```bash
sudo apt-get update
sudo apt-get upgrade
sudo apt-get install make git zlib1g-dev libssl-dev gperf cmake g++
git clone --recursive https://github.com/tdlib/telegram-bot-api.git
cd telegram-bot-api
rm -rf build
mkdir build
cd build
cmake -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX:PATH=.. ..
cmake --build . --target install
cd ../..
ls -l telegram-bot-api/bin/telegram-bot-api*
```
```bash
root@yourserver:~/telegram-bot-api/build# ./telegram-bot-api  --api-id=123456 --api-hash=12345abcdefgh --local --dir=./telegram-bot-api/tg-working-dir -l /tmp/tg.log -v3
```
You will need to provide the API ID + Hash provided here : https://core.telegram.org/api/obtaining_api_id

When the Telegram-bot-api is running, it will listen (by default) on port 8081. 

The default configuration for this PHP application is to post bot instructions to 
http://localhost:8081/ &lt;Your Bot API Token&gt;

## Scheduled Execution
Once you have the Telegram bot running in a screen, you can add the start.sh script to crontab to execute every 15 minutes
```bash
*/15  *  *  *  * cd /yourpath && ./start.sh >> /tmp/debug.log 2>&1
```
## External Applications
The following external applications are used to download media and create audio files:
 - ffmpeg [required] - https://ffmpeg.org
 - youtube-dl [optional] - https://github.com/ytdl-org/youtube-dl


## Useful Links

 - https://nordicapis.com/how-to-build-your-first-telegram-bot-using-php-in-under-30-minutes/
 - https://core.telegram.org/bots#commands
 - https://stackoverflow.com/questions/52288231/how-to-send-large-file-with-telegram-bot-api
 - https://stackoverflow.com/questions/68579698/how-to-send-large-files-through-telegram-bot
 - https://core.telegram.org/bots/api#using-a-local-bot-api-server
 - https://github.com/kenorb-contrib/tg
 - https://github.com/Teleburna/pwrtelegram
