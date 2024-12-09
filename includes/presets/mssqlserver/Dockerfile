ARG VERSION=2017-latest
FROM mcr.microsoft.com/mssql/server:$VERSION

MAINTAINER Alexander Mashin alex_mashin@list.ru
LABEL description="Microsoft SQL Server image with preinstalled Northwind database for testing pirposes"
LABEL version="0.2"

########################
# Developer tools      #
########################

# install zsh and oh-my-zsh
USER root
RUN apt update && apt install -y zsh wget git \
	&& sh -c "$(wget https://raw.githubusercontent.com/robbyrussell/oh-my-zsh/master/tools/install.sh-O -)" \
	&& git clone https://github.com/zsh-users/zsh-autosuggestions ~/.zsh/zsh-autosuggestions \
	&& echo "source ~/.zsh/zsh-autosuggestions/zsh-autosuggestions.zsh" >> ~/.zshrc \
	&& echo "ZSH_THEME=af-magic" >> ~/.zshrc

# install dev tools
RUN apt install -y vim tmux

########################
# Configure SQL Server #
########################

ENV ACCEPT_EULA="Y"
ENV MSSQL_PID="Express"

ENV PATH=$PATH:/opt/mssql-tools/bin

WORKDIR "/root"

########################
# Setup Northwind Data #
########################

ARG TEST_DB_SQL_URL="https://raw.githubusercontent.com/Microsoft/sql-server-samples/master/samples/databases/northwind-pubs/instnwnd.sql"
RUN wget "$TEST_DB_SQL_URL" -O "/usr/share/$( basename $TEST_DB_SQL_URL )"
ARG TEST_DB_SQL_URL="https://raw.githubusercontent.com/Microsoft/sql-server-samples/master/samples/databases/northwind-pubs/instnwnd.sql"
ENV ACCEPT_EULA=Y \
	MSSQL_SA_PASSWORD_FILE=/run/secrets/sa_password \
	TEST_DB=Northwind
COPY --chmod=777 <<-ENTRY /bin/entrypoint.sh
	#!bin/sh
	set -eux
	( \
		sleep 90s && \
		if [ -f "\$MSSQL_SA_PASSWORD_FILE" ]; then \
			SA_PASSWD="\$(cat "\$MSSQL_SA_PASSWORD_FILE")"; \
		else \
			SA_PASSWD="\$MSSQL_SA_PASSWORD"; \
		fi && \
		/opt/mssql-tools18/bin/sqlcmd -C -S 'localhost' -U 'sa' -P "\$SA_PASSWD" -Q "CREATE DATABASE \$TEST_DB;" && \
		/opt/mssql-tools18/bin/sqlcmd -C -S 'localhost' -U 'sa' -P "\$SA_PASSWD" -d "\$TEST_DB" -i "/usr/share/\$( basename '$TEST_DB_SQL_URL' )" && \
		TEST_USER="\$(cat /run/secrets/user)" && \
		TEST_PASSWD="\$(cat /run/secrets/password)" && \
		/opt/mssql-tools18/bin/sqlcmd -C -S 'localhost' -U 'sa' -P "\$SA_PASSWD" -Q "\
			CREATE LOGIN \$TEST_USER WITH PASSWORD = '\$TEST_PASSWD'; \
			USE \$TEST_DB; \
			CREATE USER \$TEST_USER FOR LOGIN \$TEST_USER; \
			EXEC sp_addrolemember 'db_datareader', '\$TEST_USER'; \
		";
	) & \
	/opt/mssql/bin/sqlservr
ENTRY

CMD /bin/sh /bin/entrypoint.sh

EXPOSE 1433
