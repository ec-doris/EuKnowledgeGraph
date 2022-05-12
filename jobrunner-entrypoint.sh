#!/bin/bash

# Originally inspired by Brennen Bearnes jobrunner entrypoint
# https://gerrit.wikimedia.org/r/plugins/gitiles/releng/dev-images/+/refs/heads/master/common/jobrunner/entrypoint.sh

# Wait for the db to come up
/wait-for-it.sh "$DB_SERVER" -t 300
# Sometimes it appears to come up and then go back down meaning MW install fails
# So wait for a second and double check!
sleep 1
/wait-for-it.sh "$DB_SERVER" -t 300

kill_runner() {
	kill "$PID" 2> /dev/null
}
trap kill_runner SIGTERM

while true; do
  export DOLLAR='$'
  envsubst < /LocalSettings.php.template > /var/www/html/LocalSettings.php
	php maintenance/runJobs.php --memory-limit 512M --wait --maxjobs="$MAX_JOBS"  --conf /var/www/html/LocalSettings.php &
	PID=$!
	wait "$PID"
done
