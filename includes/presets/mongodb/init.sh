#!/bin/sh
set -eux
mongoimport -v --file=/docker-entrypoint-initdb.d/zips
mongosh --port 27017 --authenticationDatabase \
	-u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" \
	--eval 'use admin' \
	--eval "db.createUser({
		user: \"${MONGO_INITDB_USERNAME}\",
		pwd: \"$( cat "$MONGO_INITDB_PASSWORD_FILE" )\",
		roles: [{ role: \"read\", db: \"$MONGO_INITDB_DATABASE\" }]
	})"
