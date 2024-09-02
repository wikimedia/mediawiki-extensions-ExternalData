<?php

namespace ExternalData\Presets;

class Reference extends Base {
	/** @const string CSS_SIZE_REGEX Regular expression for CSS heights, widths, paddings and margins. */
	private const CSS_SIZE_REGEX = '/^\d+(\.\d+)?\s*(%|px|em|ex|ch|rem|lh|rlh|vw|vh|vmin|vmax|in|cm|mm|Q|pc|pt)?$/';

	/**
	 * @const array SOURCES Connections to Docker containers for testing purposes with useful multimedia programs.
	 * Use $wgExternalDataSources = array_merge( $wgExternalDataSources, Presets::test ); to make all of them available.
	 */
	public const SOURCES = [
		// man
		'man' => [
			'name' => 'man',
			'url' => 'http://man/cgi-bin/cgi.sh?topic=$topic$',
			'version url' => 'http://man/cgi-bin/version.sh',
			'params' => [ 'topic' ],
			'param filters' => [ 'topic' => '/^\w+$/' ],
			'program url' => 'https://mandoc.bsd.lv/',
			'format' => 'html with xpath',
			'postprocess' => __CLASS__ . '::stripIllegalTags'
		],

		// whatis
		'whatis' => [
			'name' => 'whatis',
			'url' => 'http://man/cgi-bin/cgi.sh?topic=$topic$&key=-f',
			'version url' => 'http://man/cgi-bin/version.sh',
			'params' => [ 'topic' ],
			'param filters' => [ 'topic' => '/^\w+$/' ],
			'program url' => 'https://mandoc.bsd.lv/',
			'format' => 'html with xpath',
			'postprocess' => __CLASS__ . '::stripIllegalTags'
		],

		// apropos
		'apropos' => [
			'name' => 'apropos',
			'url' => 'http://man/cgi-bin/cgi.sh?topic=$topic$&key=-k',
			'version url' => 'http://man/cgi-bin/version.sh',
			'params' => [ 'topic' ],
			'param filters' => [ 'topic' => '/^\w+$/' ],
			'program url' => 'https://mandoc.bsd.lv/',
			'format' => 'html with xpath',
			'postprocess' => __CLASS__ . '::stripIllegalTags'
		],

		// apk info
		'apk info' => [
			'name' => 'apk',
			'url' => 'http://apk/cgi-bin/cgi.sh?subcommand=info&package=$package$&key=-a',
			'version url' => 'http://apk/cgi-bin/version.sh',
			'params' => [ 'package' ],
			'param filters' => [ 'package' => '/^[\w-]+$/' ],
			'program url' => 'https://wiki.alpinelinux.org/wiki/Alpine_Package_Keeper',
			'format' => 'text'
		],

		// apk dot
		'apk dot' => [
			'name' => 'apk',
			'url' => 'http://apk/cgi-bin/cgi.sh?subcommand=dot&package=$package$',
			'version url' => 'http://apk/cgi-bin/version.sh',
			'params' => [ 'package' ],
			'param filters' => [ 'package' => '/^[\w-]+$/' ],
			'program url' => 'https://wiki.alpinelinux.org/wiki/Alpine_Package_Keeper',
			'format' => 'text'
		],

		// whois
		'whois' => [
			'name' => 'whois',
			'url' => 'http://whois/cgi-bin/cgi.sh?domain=$domain$',
			'version url' => 'http://whois/cgi-bin/version.sh',
			'params' => [ 'domain' ],
			'param filters' => [ 'domain' => '/^[\w\.-]+$/' ],
			'format' => 'ini',
			'delimiter' => ':',
			'invalid as comments' => true
		],

		// composer show
		'composer show' => [
			'name' => 'composer',
			'program url' => 'https://getcomposer.org/',
			'command' => '/usr/bin/composer show --available --format=json $package$',
			'params' => [ 'package' ],
			'param filters' => [ 'package' => '%^[\w/-]+$%' ],
			'format' => 'json with jsonpath',
			'env' => [ 'COMPOSER_HOME' => __DIR__ . '/../../../..' ],
			'ignore warnings' => true
		],

		'youtube-dl' => [
			'name' => 'youtube-dl',
			'program url' => 'http://ytdl-org.github.io/youtube-dl/',
			'url' => 'http://youtube-dl/cgi-bin/cgi.sh?url=$uri$',
			'format' => 'JSON with JSONpath',
			'version url' => 'http://youtube-dl/cgi-bin/version.sh',
			'params' => [ 'uri' ],
			'param filters' => [
				'uri' => __CLASS__ . '::validateUrl'
			],
			'throttle key' => 'youtube-dl',
			'throttle interval' => 30,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'options' => [ 'sslVerifyCert' => false ]
		],

		'mmdbinspect' => [
			'name' => 'mmdbinspect',
			'url' => 'http://mmdb/cgi-bin/cgi.sh?db=$db$&ip=$ip$&filter=$filter$',
			'program url' => 'https://github.com/maxmind/mmdbinspect',
			'format' => 'JSON with JSONpath',
			'version url' => 'http://mmdb/cgi-bin/version.sh',
			'params' => [ 'db' => 'City', 'ip' => '0.0.0.0', 'filter' => '' ],
			'param filters' => [
				'db' => '/^(ASN|City|Country)$/',
				'ip' => __CLASS__ . '::validateIp',
				'filter' => '/^(\w+)?(\s+\w+)*$/'
			],
			'options' => [ 'sslVerifyCert' => false ]
		],

		'tzdata' => [
			'name' => 'tzdata',
			'program url' => 'https://www.iana.org/time-zones',
			'url' => 'http://tzdata/cgi-bin/cgi.sh',
			'version url' => 'http://tzdata/cgi-bin/version.sh',
			'format' => 'csv with headers'
		],

		'flags' => [
			'url' => 'https://raw.githubusercontent.com/lipis/flag-icons/main/flags/4x3/$iso2$.svg',
			'params' => [ 'iso2' => 'ru', 'width' => '2em', 'height' => '2ex' ],
			'param filters' => [
				'iso2' => '/^[a-z]{2}$/',
				'width' => self::CSS_SIZE_REGEX,
				'height' => self::CSS_SIZE_REGEX,
			],
			'format' => 'text',
			'tag' => 'flag',
			'postprocess' => [ 'ExternalData\Presets\Media::sizeSVG' ]
		],

		'pdf2txt' => [
			'name' => 'pdf2txt',
			'url' => 'http://pdfminer/cgi-bin/cgi.sh?path=$path$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://pdfminer/cgi-bin/version.sh',
			'program url' => 'https://github.com/pdfminer/pdfminer.six',
			'params' => [ 'filename', 'path' => __CLASS__ . '::localPath' ],
			'param filters' => [ 'filename' => __CLASS__ . '::fileExistsAndIsPdf' ]
		]
	];
}
