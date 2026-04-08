#!/bin/bash
#edit these to your config
TG_BOT_TOKEN='5335972749:AAGfrswU21bKgWw1DKjC6oPDKlgRyh7Yj5c'
TG_CHATID='-1001599704528'
BWDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"


TG_BOTAPI=https://api.telegram.org/bot
DATETIME="$(date +'%d-%m-%Y_%H-%M-%S')"
GZFILE=billcalc-${DATETIME}.tar.gz

#change working dir to /tmp
cd /tmp/

#compress bitwarden directory to gzfile
tar -Pczf $GZFILE $BWDIR

#update to telgeram
curl -s -F document=@$GZFILE $TG_BOTAPI$TG_BOT_TOKEN/sendDocument?chat_id=$TG_CHATID > /dev/null

#remove temp file
rm $GZFILE
