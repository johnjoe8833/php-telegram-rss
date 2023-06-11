#!/bin/bash
SERVICE="php process-rss.php"
if pgrep -f "$SERVICE" >/dev/null
then
    echo "$SERVICE already running"
else
    /usr/bin/php process-rss.php
fi
