FROM alpine:latest
LABEL maintainer="Alexander Mashin alex_mashin@list.ru"
LABEL description="Universal image for dockerising applications as HTTP CGI servers. To be used with MediaWiki extensions External Data and Mathjax"
LABEL version="0.2"

# Get busybox httpd:
RUN apk add --update --no-cache busybox-extras bash
ARG APK_BRANCH=edge
ARG APK_CDN=https://dl-cdn.alpinelinux.org/alpine
ARG APK_REPOS='main community testing'
ARG APK=
RUN set -eux && \
	for TAG in $APK_REPOS; do \
		echo "@$TAG $APK_CDN/$APK_BRANCH/$TAG" >> /etc/apk/repositories; \
	done && \
	apk update && \
	if [ -n "$APK" ]; then \
		apk add --no-cache $APK || exit 1; \
	fi

ARG NODE=
ARG NODE_GLOBAL=
RUN set -eux && \
	NODE_BUILD='npm g++ make' && \
	if [ -n "$NODE" ] || [ -n "$NODE_GLOBAL" ]; then \
		apk add --no-cache nodejs $NODE_BUILD && \
		cd /usr/local/bin && \
		npm init --yes; \
	fi && \
	if [ -n "$NODE_GLOBAL" ]; then \
		npm install --omit=dev -g npm $NODE_GLOBAL; \
	fi && \
	if [ -n "$NODE" ]; then \
		npm install --omit=dev $NODE; \
	fi && \
	if [ -d /usr/local/bin/node_modules/puppeteer ]; then \
		echo '{ "headless": 1, "timeout": 30000, "executablePath": "/usr/bin/chromium", "args": [ "--no-sandbox", "--disable-gpu" ] }' > /usr/local/bin/.puppeteerrc.json; \
	fi && \
	if [ -n "$NODE" ] || [ -n "$NODE_GLOBAL" ]; then \
		npm cache clean --force && \
		apk del $NODE_BUILD; \
	fi

ARG PIP=
RUN set -eux && \
	if [ -n "$PIP" ]; then \
		apk add --no-cache py3-pip && \
		python -m pip install --break-system-packages $PIP; \
	fi

ARG GO=
ENV GOPATH=/usr/local/go
RUN set -eux && \
	if [ -n "$GO" ]; then \
		apk add --no-cache go && \
		go install $GO && \
		go clean && apk del go && rm -rf "$GOPATH/pkg"; \
	fi

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
	for TGZ in *.tar.gz *.tgz; do \
		if [ -f "$TGZ" ]; then \
			tar -xzf "$TGZ" && rm "$TGZ"; \
		fi; \
	done && \
	for ZIP in *.zip; do \
		if [ -f "$ZIP" ]; then \
			unzip "$ZIP" && rm "$ZIP"; \
		fi; \
	done
GET_ALL

ARG JAR=
RUN set -eux && \
	if [ -n "$JAR" ]; then \
		apk add --no-cache openjdk11 && mkdir -p /usr/share/java && \
		cd /usr/share/java && \
		/usr/local/bin/get_all.sh "$JAR"; \
	fi

ARG BINARY=
RUN set -eux && \
	if [ -n "$BINARY" ]; then \
		cd /usr/local/bin && \
		/usr/local/bin/get_all.sh "$BINARY" && \
		for FILE in *; do \
			chmod a+rx "$FILE"; \
		done; \
	fi

ARG WGET=
RUN set -eux && \
	if [ -n "$WGET" ]; then \
		mkdir -p /usr/share/downloads && cd /usr/share/downloads && \
		/usr/local/bin/get_all.sh "$WGET"; \
	fi

ARG SRC='' \
	GIT='' \
	BRANCH=master \
	SRC_LANG=C \
	BUILD_VERBOSE='' \
	CXXFLAGS='' \
	PRE_BUILD_COMMAND='' \
	BUILD_COMMAND='' \
	BUILDER='' \
	CMAKE_ARGS=''
RUN set -eux && \
	if [ -n "$SRC" ] || [ -n "$GIT" ]; then \
		env && \
		if [ -z "$BUILDER" ]; then \
			if [ $SRC_LANG == 'go' ]; then \
				BUILDER='go make'; \
			elif [ $SRC_LANG == 'C' ]; then \
				BUILDER='boost-dev coreutils flex g++ libtool musl-dev pkgconf bison ocaml ocamlbuild make'; \
			fi; \
		fi && \
		if [ -n "$GIT" ] || [ $SRC_LANG == 'go' ]; then \
			BUILDER="$BUILDER git"; \
		fi && \
		apk add --no-cache $BUILDER && \
		mkdir -p /usr/local/bin /src && cd /src && \
		if [ -n "$GIT" ]; then \
			for URL in $GIT; do \
				git clone --depth=1 --single-branch --branch="$BRANCH" --recurse-submodules "$URL"; \
			done; \
		fi && \
		if [ -n "$SRC" ]; then \
			/usr/local/bin/get_all.sh "$SRC"; \
		fi && \
		for DIR in ./*/; do \
			if [ "$DIR" == './usr/' ]; then \
				continue; \
			fi && \
			DIR=${DIR%/*} && cd "/src/$DIR" && \
			if [ -z "$BUILD_COMMAND" ]; then \
				if [ ! -f './configure' ] && [ ! -f './Makefile' ] && [ ! -f './Makefile.am' ] && [ -d ./src ]; then \
					cd src; \
				fi && \
				if [ -f './configure.ac' ]; then \
					apk add --no-cache autoconf automake && \
					autoreconf; \
				fi && \
				if [ -f './configure' ]; then \
					apk add --no-cache autoconf && \
					./configure && \
					apk del autoconf automake; \
				elif [ -f CMakeLists.txt ]; then \
					apk add --no-cache cmake && \
					cmake $CMAKE_ARGS . ; \
				fi && \
				if [ -n "$PRE_BUILD_COMMAND" ]; then \
					/bin/sh -c "$PRE_BUILD_COMMAND"; \
				fi && \
				if [ -f ./Makefile.am ]; then \
					apk add --no-cache automake; \
				fi && \
				if [ -f './Makefile' ] || [ -f './Makefile.*' ]; then \
					apk add --no-cache make && \
					{ if [ -z "$BUILD_VERBOSE" ]; then \
						make -j; \
					else \
						make -j1; \
					fi || exit 126; } && ( \
						make install || \
						for FILE in ./*; do \
							if [ -x "$FILE" ]; then \
								cp "$FILE" "/usr/bin/"; \
							fi; \
						done \
					) && ( make clean || true ); \
				fi; \
			else \
				/bin/sh -c "$BUILD_COMMAND"; \
			fi; \
		done && \
		apk del $BUILDER automake cmake && cd / && rm -r /src; \
	fi

ARG STARTUP=
RUN set -eux && \
	if [ -n "$STARTUP" ]; then \
		/bin/bash -c "$STARTUP"; \
	fi

ARG SCRIPT=
RUN set -eux && \
	if [ -n "$SCRIPT" ]; then \
		echo "$SCRIPT" > /usr/local/bin/script && chmod +x /usr/local/bin/script; \
	fi

RUN set -eux && apk cache clean

COPY --chmod=777 <<-'FUNC' /usr/local/bin/cgi_functions.sh
	#!/bin/bash
	function urldecode() {
		httpd -d "$@"
	}

	# Parse query strings, set variables:
	function parse_query() {
		local QUERY_STRING="$1"

		local SAVE_IFS=$IFS
		IFS='&'
		local ASSIGNMENTS=($QUERY_STRING)
		for (( i=0; i<${#ASSIGNMENTS[@]}; i+=1 )); do
			IFS='='
			local PAIR=(${ASSIGNMENTS[i]})
			local KEY=${PAIR[0]}
			local VALUE=${PAIR[1]}
			for (( j=2; j<${#PAIR[@]}; j+=1 )); do
				VALUE="$VALUE=${PAIR[j]}"
			done
			declare -g "$KEY"="$( urldecode "$VALUE" )"
		done
		IFS=\$SAVE_IFS
	}

	function debug_info() {
		local COMMAND="$1"
		local FILTER_COMMAND="$2"
		local QUERY_STRING="$3"
		local REQUEST_METHOD="$4"
		local STDOUT="$5"
		local STDERR="$6"
		local STDIN="$7"

		echo "Query was $QUERY_STRING"; echo ''
		echo "Command is $COMMAND"; echo ''
		echo "Filter command is $FILTER_COMMAND"; echo ''
		if [ -n "$STDIN" ]; then
			echo "stdin: $STDIN"
		fi
		echo 'stdout:'; cat "$STDOUT"; echo ''
		echo 'stderr:'; cat "$STDERR"; echo ''
	}

	# If parameters do not pass the filter or debug mode is on:
	function filter_result() {
		local STDOUT="$1"
		local STDERR="$2"
		local DEBUG="$3"
		local COMMAND="$4"
		local FILTER_COMMAND="$5"
		local QUERY_STRING="$6"
		local REQUEST_METHOD="$7"

		if [ -s "$STDERR" ]; then
			echo 'Status: 502 Bad Gateway'; echo '';
			echo 'Wrong query arguments'; echo ''
			if [ -n "$DEBUG" ]; then
				debug_info "$COMMAND" "$FILTER_COMMAND" "$QUERY_STRING" "$REQUEST_METHOD" "$STDOUT" "$STDERR"
			fi
		else
			if [ -n "$DEBUG" ]; then
				echo 'Status: 502 Bad Gateway'; echo '';
				debug_info "$COMMAND" "$FILTER_COMMAND" "$QUERY_STRING" "$REQUEST_METHOD" "$STDOUT" "$STDERR"
			fi
		fi
		rm -f "$STDOUT" "$STDERR"
	}

	function send_text() {
		local CONTENT_TYPE="$1"
		local STDOUT="$2"
		local STDERR="$3"
		local DEBUG="$4"
		local ERRORS="$5"
		local COMMAND="$6"
		local FILTER_COMMAND="$7"
		local QUERY_STRING="$8"
		local REQUEST_METHOD="$9"
		local STDIN="${10}"

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

	function send_headers_for_binary(){
		local CONTENT_TYPE="$1"
		echo "Content-type: $CONTENT_TYPE;"
		echo 'Status: 200 OK';
		echo '';
	}
FUNC

ARG COMMAND="echo 'Environment variables:'; env; echo 'Standard input:'; cat"
ARG FILTER_COMMAND=''
ARG CONTENT_TYPE='text/plain'
ARG CGI=cgi.sh
ENV BINARY=
ENV ERRORS=ALL
ENV DEBUG=''
COPY --chmod=777 <<-CGI /www/cgi-bin/$CGI
	#!/bin/bash
	# Do not set -eux.
	# ${COMMAND//
/
# }
	# will receive stdin from /www/cgi-bin/$CGI
	# https://oldforum.puppylinux.com/viewtopic.php?t=115252

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

	STDIN=''
	if [ "\${REQUEST_METHOD:-}" == 'GET' ]; then
		# Close stdin:
		exec 0<&-
	elif [ -z "\$BINARY" ]; then
		STDIN=\$( cat )
	fi

	# Apply FILTER_COMMAND to check variables:
	if [ -n '$FILTER_COMMAND' ] && [ -z "\$BINARY" ]; then
		STDOUT=\$(mktemp -u)
		STDERR=\$(mktemp -u)
		( :; $FILTER_COMMAND ) <<< "$STDIN" 1>"\$STDOUT" 2>"\$STDERR"
		filter_result "\$STDOUT" "\$STDERR" "\$DEBUG" \\
			"\$COMMAND" "\$FILTER_COMMAND" "\$QUERY_STRING" "\$REQUEST_METHOD"
	fi

	CONTENT_TYPE="$CONTENT_TYPE"
	# Actually run the command:
	if [ -z "\$BINARY" ]; then
		STDOUT=\$(mktemp -u)
		STDERR=\$(mktemp -u)
		( $COMMAND ) <<< "\$STDIN" 1>"\$STDOUT" 2>"\$STDERR"

		send_text "\$CONTENT_TYPE" "\$STDOUT" "\$STDERR" "\$DEBUG" "\$ERRORS" \\
			"\$COMMAND" "\$FILTER_COMMAND" "\$QUERY_STRING" "\$REQUEST_METHOD" "\$STDIN"
	else
		send_headers_for_binary "\$CONTENT_TYPE"
		if [ -n "\$DEBUG" ] || [ "\$ERRORS" == 'ALL' ]; then
			( $COMMAND ) 2>&1
		else
			( $COMMAND ) 2>/dev/null
		fi
	fi

CGI

ARG COMMAND="echo 'Environment variables:'; env; echo 'Standard input:'; cat"
ARG VERSION_COMMAND=
COPY --chmod=777 <<-VERSION /www/cgi-bin/version.sh
	#!/bin/sh
	set -eu
	# This is to reliably escape any quotes:
	COMMAND=$( cat <<-'SCMD'
		$COMMAND
	SCMD
	)
	VERSION_COMMAND=$( cat <<-'VCMD'
		$VERSION_COMMAND
	VCMD
	)
	echo 'Content-type: text/plain; charset=UTF-8'
	echo 'Status: 200 OK'
	echo ''
	if [ -n "\$VERSION_COMMAND" ]; then
		VC="\$VERSION_COMMAND"
	else
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
# EXPOSE 443

RUN adduser -D www-data -G www-data
USER www-data
ENTRYPOINT ["/usr/local/bin/start.sh"]
