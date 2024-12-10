FROM alpine:latest
MAINTAINER Alexander Mashin alex_mashin@list.ru
LABEL description="Universal image for dockerising applications as HTTP CGI servers. To be used with MediaWiki extension External Data"
LABEL version="0.1"

# Get busybox httpd:
RUN apk add --update --no-cache busybox-extras bash
ARG APK
RUN <<-APKADD
	set -ex && \
	if [ ! -z "$APK" ]; then
		apk add --no-cache $APK
	fi
APKADD

ARG NODE
ARG NODE_GLOBAL
RUN <<-NODEJS
	set -ex && \
	if [ ! -z "$NODE" ] || [ ! -z "$NODE_GLOBAL" ]; then \
		apk add --no-cache npm && \
		cd /usr/local/bin && \
		npm init --yes; \
	fi && \
	if [ ! -z "$NODE_GLOBAL" ]; then \
		npm install -g $NODE_GLOBAL; \
	fi &&
	if [ ! -z "$NODE" ]; then \
		npm install $NODE; \
	fi &&
	if [ -d /usr/local/bin/node_modules/puppeteer ]; then \
		echo '{ "headless": 1, "timeout": 30000, "executablePath": "/usr/bin/chromium", "args": [ "--no-sandbox", "--disable-gpu" ] }' > /usr/local/bin/.puppeteerrc.json; \
	fi
NODEJS

ARG PIP
RUN <<-PIPINSTALL
	set -ex && \
	if [ ! -z "$PIP" ]; then \
		apk add --no-cache py3-pip && \
		python -m  pip install --break-system-packages $PIP; \
	fi
PIPINSTALL

ARG GO
ENV GOPATH=/usr/local/go
RUN <<-GOINSTALL
	set -ex && \
	if [ ! -z "$GO" ]; then \
		apk add --no-cache go && \
		go install $GO && \
		go clean && apk del go; \
	fi
GOINSTALL

# Downloads all URLS; then replaces all archives in current directory with their contents:
COPY --chmod=777 <<-'GET_ALL' /usr/local/bin/get_all.sh
#!/bin/sh
	set -eux
	URLS="$1"
	for URL in $URLS; do \
		wget "$URL"; \
	done && \
	for FILE in *\?*; do \
		if [ -f "$FILE" ]; then \
			mv "$FILE" "${FILE%\?*}"; \
		fi; \
	done && \
	for TAR_GZ in *.tar.gz; do \
		if [ -f "$TAR_GZ" ]; then \
			tar -xzf "$TAR_GZ" && rm "$TAR_GZ"; \
		fi; \
	done && \
	for ZIP in *.zip; do \
		if [ -f "$ZIP" ]; then \
			unzip "$ZIP" && rm "$ZIP"; \
		fi; \
	done
GET_ALL

ARG JAR
RUN <<-JAR
	set -ex && \
	if [ ! -z "$JAR" ]; then \
		apk add --no-cache openjdk11 && mkdir -p /usr/share/java && \
		cd /usr/share/java && \
		/usr/local/bin/get_all.sh "$JAR"; \
	fi
JAR

ARG BINARY
RUN <<-BINARY
	set -ex && \
	if [ ! -z "$BINARY" ]; then \
		cd /usr/local/bin && \
		/usr/local/bin/get_all.sh "$BINARY" && \
		for FILE in *; do \
			chmod a+rx "$FILE"; \
		done; \
	fi
BINARY

ARG WGET
RUN <<-WGET
	set -ex && \
	if [ ! -z "$WGET" ]; then \
		mkdir -p /usr/share/downloads && cd /usr/share/downloads && \
		/usr/local/bin/get_all.sh "$WGET"; \
	fi
WGET

ARG SRC
ARG GIT
ARG BRANCH=master
ARG SRC_LANG=C
RUN <<-SRC
	set -ex && \
	if [ ! -z "$SRC" ] || [ ! -z "$GIT" ]; then \
		if [ $SRC_LANG == 'go' ]; then \
			BUILD='go make git'; \
		elif [ $SRC_LANG == 'C' ]; then \
			BUILD='flex bison gcc musl-dev make'; \
		fi && \
		if [ ! -z "$GIT" ]; then \
			BUILD="$BUILD git"; \
		fi && \
		apk add --no-cache $BUILD && \
		mkdir -p /usr/local/bin && mkdir -p /src && cd /src && \
		if [ ! -z "$GIT" ]; then \
			for URL in $GIT; do \
				git clone --single-branch --branch="$BRANCH" "$URL"; \
			done; \
		else \
			if [ ! -z "$SRC" ]; then \
				/usr/local/bin/get_all.sh "$SRC"; \
			fi; \
		fi && \
		for DIR in ./*/; do \
			DIR=${DIR%/*} && cd "$DIR" && \
			if [ ! -f './configure' ] && [ ! -f './Makefile' ] && [ ! -f './Makefile.*' ]; then \
				cd src; \
			fi && \
			if [ -f './configure' ]; then \
				./configure; \
			fi && \
			make && ( \
				make install || \
				for FILE in ./*; do \
					if [ -f "$FILE" ] && [ -x "$FILE" ]; then \
						cp "$FILE" "/usr/bin/";
					fi; \
				done \
			) && ls -la . && make clean; \
		done && \
		apk del $BUILD && cd / && rm -r /src
	fi
SRC

ARG STARTUP
RUN set -ex && ( : ; $STARTUP )

ARG SCRIPT
RUN <<-SCRIPT
	set -ex && \
	if [ ! -z "$SCRIPT" ]; then \
		echo "$SCRIPT" > /usr/local/bin/script && chmod +x /usr/local/bin/script; \
	fi
SCRIPT

COPY --chmod=777 <<-'FUNC' /usr/local/bin/cgi_functions.sh
	#!/bin/bash
	function urldecode() {
		httpd -d "$@"
	}

	# Parse query strings, set variables:
	function parse_query() {
		QUERY_STRING="$1"

		SAVE_IFS=$IFS
		IFS='&'
		ASSIGNMENTS=($QUERY_STRING)
		for (( i=0; i<${#ASSIGNMENTS[@]}; i+=1 )); do
			IFS='='
			PAIR=(${ASSIGNMENTS[i]})
			KEY=${PAIR[0]}
			VALUE=${PAIR[1]}
			for (( j=2; j<${#PAIR[@]}; j+=1 )); do
				VALUE="$VALUE=${PAIR[j]}"
			done
			declare -g "$KEY"="$( urldecode "$VALUE" )"
		done
		IFS=\$SAVE_IFS
	}

	function debug_info() {
		COMMAND="$1"
		FILTER_COMMAND="$2"
		QUERY_STRING="$3"
		REQUEST_METHOD="$4"
		STDOUT="$5"
		STDERR="$6"
		STDIN="$7"

		echo "Query was $QUERY_STRING"; echo ''
		echo "Command is $COMMAND"; echo ''
		echo "Filter command is $FILTER_COMMAND"; echo ''
		if [ ! -z "$STDIN" ]; then
			echo "stdin: $STDIN"
		fi
		echo 'stdout:'; cat "$STDOUT"; echo ''
		echo 'stderr:'; cat "$STDERR"; echo ''
	}

	# If parameters do not pass the filter or debug mode is on:
	function filter_result() {
		STDOUT="$1"
		STDERR="$2"
		DEBUG="$3"
		COMMAND="$4"
		FILTER_COMMAND="$5"
		QUERY_STRING="$6"
		REQUEST_METHOD="$7"

		if [ -s "$STDERR" ]; then
			echo 'Status: 502 Bad Gateway'; echo '';
			echo 'Wrong query arguments'; echo ''
			if [ ! -z "$DEBUG" ]; then
				debug_info "$COMMAND" "$FILTER_COMMAND" "$QUERY_STRING" "$REQUEST_METHOD" "$STDOUT" "$STDERR"
			fi
		else
			if [ ! -z "$DEBUG" ]; then
				echo 'Status: 502 Bad Gateway'; echo '';
				debug_info "$COMMAND" "$FILTER_COMMAND" "$QUERY_STRING" "$REQUEST_METHOD" "$STDOUT" "$STDERR"
			fi
		fi
		rm -f "$STDOUT" "$STDERR"
	}

	function command_result() {
		CONTENT_TYPE="$1"
		STDOUT="$2"
		STDERR="$3"
		DEBUG="$4"
		ERRORS="$5"
		COMMAND="$6"
		FILTER_COMMAND="$7"
		QUERY_STRING="$8"
		REQUEST_METHOD="$9"
		STDIN="${10}"

		if [ -z "$DEBUG" ]; then
			if [ -s "$STDOUT" ] && ( [ ! -s "$STDERR" ] || [ "$ERRORS" == 'FATAL' ] ) || [ "$ERRORS" == 'IGNORE' ]; then
				echo "Content-type: $CONTENT_TYPE; charset=UTF-8"
				echo 'Status: 200 OK';
				echo '';
				cat "$STDOUT"
			else
				echo 'Status: 502 Bad Gateway';
				echo '';
				cat "$STDERR"
			fi
		else
			echo 'Status: 502 Bad Gateway'; echo '';
			echo 'Debug mode'
			debug_info "$COMMAND" "$FILTER_COMMAND" "$QUERY_STRING" "$REQUEST_METHOD" "$STDOUT" "$STDERR" "$STDIN"
		fi
		cat "$STDERR" > /dev/stderr
		rm -f "$STDOUT" "$STDERR"
	}
FUNC

ARG COMMAND="echo 'Environment variables:'; env; echo 'Standard input:'; cat"
ARG FILTER_COMMAND=''
ARG CONTENT_TYPE='text/plain'
ARG CGI=cgi.sh
ENV ERRORS=ALL
ENV DEBUG=''
COPY --chmod=777 <<-CGI /www/cgi-bin/$CGI
	#!/bin/bash
	: '
	Do not set -eux.
	$COMMAND will receive stdin from /www/cgi-bin/\$SCRIPT
	https://oldforum.puppylinux.com/viewtopic.php?t=115252
	'

	source /usr/local/bin/cgi_functions.sh

	# Parse query strings, set variables:
	parse_query "\${QUERY_STRING:-}"

	# This is to reliably escape any quotes:
	COMMAND=$( cat <<-'SCMD'
		$COMMAND
	SCMD
	)
	FILTER_COMMAND=$( cat <<-'FCMD'
		$FILTER_COMMAND
	FCMD
	)

	if [ "\${REQUEST_METHOD:-}" == 'GET' ]; then
		# Close stdin:
		exec 0<&-
		STDIN=''
	else
		STDIN=\$( cat )
	fi

	# Apply FILTER_COMMAND to check variables:
	if [ ! -z '$FILTER_COMMAND' ]; then
		STDOUT=\$(mktemp -u)
		STDERR=\$(mktemp -u)
		( :; $FILTER_COMMAND ) <<< "$STDIN" 1>"\$STDOUT" 2>"\$STDERR"
		filter_result "\$STDOUT" "\$STDERR" "\$DEBUG" \\
			"\$COMMAND" "\$FILTER_COMMAND" "\$QUERY_STRING" "\$REQUEST_METHOD"
	fi

	STDOUT=\$(mktemp -u)
	STDERR=\$(mktemp -u)

	# Actually run the command:
	( $COMMAND ) <<< "\$STDIN" 1>"\$STDOUT" 2>"\$STDERR"

	command_result '$CONTENT_TYPE' "\$STDOUT" "\$STDERR" "\$DEBUG" "\$ERRORS" \\
		"\$COMMAND" "\$FILTER_COMMAND" "\$QUERY_STRING" "\$REQUEST_METHOD" "\$STDIN"

CGI

ARG COMMAND="echo 'Environment variables:'; env; echo 'Standard input:'; cat"
ARG VERSION_COMMAND
COPY --chmod=777 <<-VERSION /www/cgi-bin/version.sh
	#!/bin/sh
	set -eu
	echo 'Content-type: text/plain; charset=UTF-8'
	echo 'Status: 200 OK'
	echo ''
	if [ ! -z '$VERSION_COMMAND' ]; then
		VC='$VERSION_COMMAND'
	else
		COMMAND='$COMMAND'
		VC="\${COMMAND%% *} --version || \${COMMAND%% *} -v || \${COMMAND%% *} -V"
	fi
	eval "\$VC" 2>&1 # Some programs, e.g., graphviz, print version info to stderr out of principle.
VERSION

COPY --chmod=777 <<-'START' /usr/local/bin/start.sh
	#!/bin/sh
	set -eux
	exec httpd -v -p 80 -h /www -f
START

EXPOSE 80

RUN adduser -D www-data -G www-data
USER www-data
ENTRYPOINT ["/usr/local/bin/start.sh"]
