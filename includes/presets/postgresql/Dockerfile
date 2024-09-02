ARG VERSION=16.4
FROM postgres:$VERSION-alpine

MAINTAINER Alexander Mashin alex_mashin@list.ru
LABEL description="PostrgreSQL image with preinstalled Northwind database for testing pirposes"
LABEL version="0.2"

ARG TEST_DB_SQL_URL=https://www.postgresqltutorial.com/wp-content/uploads/2019/05/dvdrental.zip
RUN ZIP="$(basename "$TEST_DB_SQL_URL")" && \
	wget "$TEST_DB_SQL_URL" -O "/usr/share/$ZIP" && \
	unzip "/usr/share/$ZIP" -d /usr/share && \
	rm "/usr/share/$ZIP"

ARG TEST_DB_SQL_URL=https://www.postgresqltutorial.com/wp-content/uploads/2019/05/dvdrental.zip
ENV POSTGRES_PASSWORD_FILE=/run/secrets/postgres_password

COPY --chmod=777 <<-ENTRY /docker-entrypoint-initdb.d/install_test.sh
	#!bin/sh
	set -eux
	
	# Restore the dvdrental database:
	URL='$TEST_DB_SQL_URL'
	TEST_DB="\$(basename \${URL%%.zip})"
	psql -U postgres -c "CREATE DATABASE \$TEST_DB;"
	pg_restore -U postgres -d "\$TEST_DB" "/usr/share/\$TEST_DB.tar"
	
	# Give the test user reading rights on dvdrental:
	TEST_USER="\$(cat /run/secrets/user)"
	TEST_PASSWD="\$(cat /run/secrets/password)"

	psql -U postgres -d "\$TEST_DB" -c " \
		CREATE ROLE \$TEST_USER WITH LOGIN PASSWORD '\$TEST_PASSWD'; \
		GRANT CONNECT ON DATABASE \$TEST_DB TO \$TEST_USER; \
		GRANT SELECT ON ALL TABLES IN SCHEMA public TO \$TEST_USER; \
	"
ENTRY
