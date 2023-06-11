<?php

// configure the bot path (the Token provided by BotFather) and chatID (the Channel ID your bot is posting to)
$botpath = "http://localhost:8081/bot12345678:ABCDEFGHIJKLMNOPQ";
$chatId = "-1234567890123456";

$feeds = json_decode(file_get_contents("feeds.json"), true);
$compression = "-qscale:a 5 -ar 22050";
ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');

$items = array();
echo sprintf("Processing : %d feeds @ %s\n", count($feeds), date('Y-m-d H:i:s'));
foreach ($feeds as $t) {
    if ($t["active"]) {
        $ret = @file_get_contents($t["url"]);
        if ($ret) {
            $xml = @simplexml_load_string($ret);
            if ($xml) {

                if (isset($t["type"]) && $t["type"] == "youtube") {
                    $channelitems = $xml->entry;
                } else {
                    $channelitems = $xml->channel->item;
                }

                foreach ($channelitems as $i) {
                    $failed = false;
                    $item = array();

                    $item["type"] = isset($t["type"]) ? $t["type"] : "video" ;
                    if ((string)$i->guid != "") {
                        $item["guid"] = md5((string)$i->guid);
                    } else {
                        $item["guid"] = md5((string)$i->id);
                    }
                    $item["link"] = (string)$i->link;
                    if ($item["link"] == "") {
                        $item["link"] = (string)$i->link["href"];
                    }

                    $item["length"] = 0;

                    if (preg_match("/bitchute/i", $t["url"])) {
                        if (preg_match("/embed\/(.*)\//i", (string)$i->link, $matches)) {
                            $item["fileurl"] = sprintf("https://seed122.bitchute.com/%s/%s.mp4", $t["bitchute-channelid"], $matches[1]);
                            $item["link"] = sprintf("https://www.bitchute.com/video/%s", $matches[1]);
                        } else {
                            $failed = true;
                        }
                    } else {
                        $item["fileurl"] = (string)$i->enclosure["url"];
                        $item["length"] = (string)$i->enclosure["length"];
                    }

                    $item["title"] = (string)$i->title;
                    $item["date"] = (string)$i->pubDate;
                    if ($item["date"] == "") {
                        $item["date"] = (string)$i->published;
                    }

                    $item["formattedtitle"] = formatTitle((string)$item["title"]);
                    $item["performer"] = $t["performer"];
                    $item["icon"] = $t["icon"];
                    $item["chatid"] = isset($t["chatid"]) ? $t["chatid"] : $chatId ;
                    $item["compression"] = isset($t["compression"]) ? $t["compression"] : $compression ;
                    $item["extension"] = pathinfo($item["fileurl"], PATHINFO_EXTENSION);
                    $item["dlfilename"] = safeFilename(sprintf("%s_%s_%s", $item["performer"], $item["formattedtitle"], strftime("%Y_%m_%d", strtotime($item["date"]))));
                    $item["marker"] = "guid/".$item["guid"].".txt";
                    $item["notifytxt"] = str_replace("[longdate]", date("jS M Y", strtotime($item["date"])), str_replace("[link]", $item["link"], str_replace("[title]", $item["formattedtitle"], $t["desc"])));
                    $item["outvideofile"] = sprintf("tmp/%s.%s", $item["dlfilename"], $item["extension"]);
                    $item["outaudiofile"] = sprintf("out/%s.mp3", $item["dlfilename"]);

                    if ((!isset($t["match"]) || isset($t["match"]) && preg_match($t["match"], $item["title"]))
                        && !file_exists($item["marker"])
                        && !$failed
                        && (!isset($t["datelimit"]) || isset($t["datelimit"]) && strtotime($item["date"]) > strtotime($t["datelimit"])) ){
                        $items[] = $item;
                    }
                }
            }
        }
    }
}

foreach ($items as $i) {
    echo sprintf("Marking : %s\n", $i["outvideofile"]);
    file_put_contents($i["marker"], $i["outvideofile"]);

    if (!file_exists($i["outaudiofile"])) {
        if ($i["type"] == "audio") {

            echo sprintf("Fetching : %s\n", $i["dlfilename"]);
            file_put_contents($i["outaudiofile"], fopen($i["fileurl"], 'r'));

        } elseif ($i["type"] == "youtube") {

            $ytcmd = sprintf("./youtube-dl -x --audio-format mp3 --audio-quality 4 -o %s %s >> /tmp/dl.log 2>&1", $i["outaudiofile"], $i["link"]);
            echo sprintf("YouTube-DL : %s %s\n", $i["dlfilename"], $ytcmd);
            runExternalCmd($ytcmd);

        } else {

            echo sprintf("Fetching : %s\n", $i["dlfilename"]);
            file_put_contents($i["outvideofile"], fopen($i["fileurl"], 'r'));
            echo sprintf("Downloaded : %s Size %d Expected %d\n", $i["outvideofile"], @filesize($i["outvideofile"]), $i["length"]);

            if ($i["length"] > 0) {
                $badsize = false;
                $sizeondisk = @filesize($i["outvideofile"]);
                $percentagedifference = 0;
                if ($sizeondisk) {
                    $percentagedifference = round((($sizeondisk - $i["length"])  / (($sizeondisk + $i["length"]) / 2) * 100))*-1;
                    if ($percentagedifference > 10) {
                        $badsize = true;
                    }
                } else {
                    $badsize = true;
                }

                if ($badsize) {
                    echo "Badsize : Expected " . $i["length"] . " Got " . $sizeondisk . " % Diff " . $percentagedifference . "\n";
                    @unlink(realpath($i["outvideofile"]));
                    @unlink(realpath($i["marker"]));
                    continue;
                }
            }

            $audiocmd = sprintf("/usr/bin/ffmpeg -i %s -codec:a libmp3lame %s -y %s", $i["outvideofile"], $i["compression"], $i["outaudiofile"]);
            echo sprintf("Muxing : %s\n", $audiocmd);
            runExternalCmd($audiocmd);
        }
    }

    echo sprintf("Telegramming : %s : %s\n", $i["outaudiofile"], $i["notifytxt"]);
    postTelegram($i, $i["chatid"], $botpath);

    echo sprintf("Cleaning : \n");
    $cleancmd = "rm -rf tmp/*";
    runExternalCmd($cleancmd);
}

echo sprintf("Finished : @ %s\n", date('Y-m-d H:i:s'));


function safeFilename($filename) {
    $temp = $filename;

    $result = '';
    for ($i=0; $i<strlen($temp); $i++) {
        if (preg_match('([a-zA-Z0-9\s\.\-\_])', $temp[$i])) {
            $result = $result.$temp[$i];
        }
    }

    return str_replace(" ", "_", $result);
}

function formatTitle($title) {

    $ret = trim(preg_replace("/[^a-zA-Z0-9\@\s\p{Pd}\p{Ps}\p{Pe}\p{Pi}\p{Pf}\p{Pc}]/", "", $title));

    if (ctype_upper(preg_replace("/[^a-zA-Z]/", "", $ret))) {
        $ret = ucwords(strtolower($ret));
    }

    return $ret;
}

function runExternalCmd($command, $debug=false, $blockoutput=false) {

    if ($blockoutput) $command .= " > /dev/null 2>&1";
    if ($debug) error_log(':Running Command: '.$command);

    $output = array();
    $status = 1;
    @exec($command, $output, $status);

    return $output;
}

function postTelegram($item, $chatId, $botpath) {

    $url = $botpath."/sendAudio?chat_id=".$chatId;
    $post_fields = array('chat_id' => $chatId,
        'audio' => new CURLFile(realpath($item["outaudiofile"])),
        'caption' =>  $item["notifytxt"],
        'performer' => $item["performer"],
        'thumb' => new CURLFile(realpath($item["icon"])),
        'title' => $item["formattedtitle"],
        'parse_mode' => 'html'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $ret = curl_exec($ch);
    curl_close($ch);
}
