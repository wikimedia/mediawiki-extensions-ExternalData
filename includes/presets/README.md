This directory contains presets that can be used as
External Data sources (`$wgExternalDataSources` items):

 - ExternalData\Presets\Base contains some functions that can be
used as pre- and postprocessors;
 - ExternalData\Presets\Test contains connectors to Dockerised
 example databases as setup in `docker-compose.yml`
(add `$wgExternalDataSources['load test presets'] = true;` to
`LocalSettings.php` to activate them all). When applicable,
there are separate data sources with prepared statements:
   - _Microsoft SQL Server_ with `NorthWind` database,
   - _PostgreSQL_ with `dvdrental` database,
   - _MongoDB_ with USA zip codes database,
   - [Rfam](https://rfam.org/) _mySQL_ database (remote),
   - current wiki _mySQL_ database,
   - a LDAP server at `ldap.forumsys.com`,
   - a test file data source representing this extension's
 `extension.json`,
   - a directory walker source connecting to this extension
 directory, that can be used (with `<graphviz>`) to draw
 extension's class hierarchy;
 - ExternalData\Presets\Reference contains presets for
some Dockerised reference programs
(add `$wgExternalDataSources['load reference presets'] = true;`
to `LocalSettings.php` to activate them all):
   - `man`, `whatis`, `apropos`, `apk info`, `apk dot` (best used
 with `<graphviz>`), `whois`, `composer show`, `youtube-dl`,
 `mmdblookup`, `tzdata`, `pdf2txt`, as well as
 a [remote source](https://github.com/lipis/flag-icons)
 of national flags in SVG format;
 - ExternalData\Presets\Media contains presets for some
Dockerised multimedia programs, invoked as MediaWiki tags,
mostly supporting wikilinks and interactive, if this
feature is provided by the program
(add `$wgExternalDataSources['load media presets'] = true;`
to `LocalSettings.php` to activate them all):
   - `<mathjax>` ([MathJax](https://www.mathjax.org/), partially emulating [MathJax MW extension](https://github.com/alex-mashin/MathJax)),
   - `<barcode>` ([Zint](https://www.zint.org.uk)),
   - `<score>` ([LilyPond](http://lilypond.org/)),
   - `<graphviz>` ([GraphViz](https://graphviz.org/)),
   - `<mscgen>` ([mscgen](https://www.mcternan.me.uk/mscgen/)),
   - `<plantuml>` ([PlantUML](https://plantuml.com)),
   - `<ploticus>` ([ploticus](http://ploticus.sourceforge.net/doc/welcome.html)),
   - `<timeline>` ([EasyTimeline](http://infodisiac.com/Wikipedia/EasyTimeline/Introduction.htm)),
   - `<gnuplot>` ([gnuplot](http://www.gnuplot.info/)),
   - `<graph>` ([Vega](https://vega.github.io)),
   - `<mermaid>` ([mermaid](https://mermaid-js.github.io)),
   - `<bpmn>` ([bpmn2svg by Pierre Schwang](https://github.com/PierreSchwang/bpmn2svg)),
   - `<echarts>` ([Apache ECharts](https://echarts.apache.org)).

A single data source can be connected like this:
```php
$wgExternalDataSources['some media service'] = 'media';
```
or this:
```php
$wgExternalDataSources['some media service'] = [
    'preset' => 'media',
    // you may add some overrides for the preset.
];
```

Most data sources are Dockerised, and the MediaWiki installation
ought to have access to the containers either by sharing
a network in the same Docker setup, or by exposing ports of the
containers and inserting them into
`$wgExternalDataSource['…']['url']` and
`$wgExternalDataSource['…']['version url']` settings.

To make `<math>`, `<gnuplot>`, `<graph>`, `<mermaid>` and
`<echarts>` tags interactive in user's browser, their containers
ought to share mounted volumes with the web server frontend,
if it is containerised, or to have bind mounts available
and servable by the frontend. The URLs, by which these shared
directories are served by the web server, are set in the
`$wgExternalDataSources['…']['scripts']` setting.

The four necessary `Dockerfile`s are:
  - `includes/presets/cgi/Dockerfile` for most of Dockerised
programs, wrapped in a web server; based on _Alpine Linux_.
It has to be built with these arguments (all space-delimited):
    - `APK` — `apk` packages to install,
    - `NODE` and `NODE_GLOBAL` — _Node.js_ packages to install,
locally and globally,
    - `PIP` — _Python_ `pip` packages to install,
    - `GO` — _Go_ packages to install
(binaries in `/usr/local/go/bin`),
    - `JAR` — _Java_ `jar` packages to install
in `/usr/share/java` (`.zip` and `.tar.gz` archives will be inflated),
    - `BINARY` — URLs of executable binaries to install
in `/usr/local/bin` (`.zip` and `.tar.gz` archives will be inflated),
    - `WGET` — URLs of files to download to
`/usr/share/downloads` (`.zip` and `.tar.gz` archives will be inflated),
    - `SRC` — URLs of source code to be downloaded and
  installed in `/usr/bin`, unless otherwise specified by their
  `Makefile`s. `.zip` and `.tar.gz` archives will be inflated,
    - `GIT` — URLs of _Git_ repositories to download, build and
install as above,
    - `SRC_LANG` — `C` (default) or `GO`, for the previous two
arguments,
    - `STARTUP` — a Linux shell command to be run at the end of
image building,
    - `SCRIPT` — Linux shell script that will be saved at
`/usr/local/bin/script`,
    - `COMMAND` — a Linux shell command that is wrapped
by this CGI server. Web query variables are available to it,
and the posted content is fed to its standard input. Its standard
output will be sent to web client, and its `stderr`, unless
errors are ignored, will be sent as an error message,
    - `FILTER_COMMAND` — a Linux shell command that can be used
to validate parameters,
    - `VERSION COMMAND` — a Linux shell command that prints
the containerised program's version,
    - `CONTENT_TYPE` — for the corresponding HTTP header,
    - `CGI` — CGI script name (`cgi.sh` by default)
    - `ERRORS` — `ALL`, `IGNORE` or `FATAL`,
    - `DEBUG` — set to `yes`, to see debug output instead of
the program output,
  - `includes/presets/mssqlserver/Dockerfile` for _MS SQL Server_
with `NorthWind` database,
  - `includes/presets/postgresql/Dockerfile` for _PostgreSQL_ with
`dvdrental` database,
  - `includes/presets/mongodb/Dockerfile` for _MongoDB_ with
USA zip code fatabase.

`includes/presets/docker-compose.yml` contains setup instructions
for the containers. It is not itself a complete valid compose
file.
