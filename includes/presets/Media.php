<?php

namespace ExternalData\Presets;

use CoreParserFunctions;
use DOMDocument;
use MediaWiki\Languages\Data\Names;
use MediaWiki\MediaWikiServices;
use MWException;
use NumberFormatter;
use function sprintf;

/**
 * Class wrapping the constant containing multimedia software data source presets purposes for autoloading.
 *
 * @author Alexander Mashin
 *
 */
class Media extends Base {
	/**
	 * @const array SOURCES Connections to Docker containers for testing purposes with useful multimedia programs.
	 * Use $wgExternalDataSources = array_merge( $wgExternalDataSources, Presets::test ); to make all of them available.
	 */
	public const SOURCES = [
		// This data source does not replace MathJax MW extension (https://github.com/alex-mashin/MathJax).
		'mathjax' => [
			'url' => 'http://mathjax/cgi-bin/cgi.sh?config=yes',
			'format' => 'text',
			'options' => [ 'sslVerifyCert' => false ],
			'version url' => 'http://mathjax/cgi-bin/version.sh',
			'name' => 'MathJax',
			'program url' => 'https://www.mathjax.org/',
			'params' => [ 'display' => 'inline', 'nomenu' => false ],
			'param filters' => [ 'display' => '/^(block|inline)$/' ],
			'input' => 'tex',
			'preprocess' => __CLASS__ . '::encloseTex',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'postprocess' => [
				__CLASS__ . '::innerHtml',
				__CLASS__ . '::addMathJaxMenu'
			],
			'scripts' => '/js/mathjax',
			'tag' => 'mathjax',
		],

		'maxima' => [
			'url' => 'http://maxima/cgi-bin/cgi.sh',
			'format' => 'text',
			'options' => [ 'sslVerifyCert' => false, 'timeout' => 90 ],
			'version url' => 'http://maxima/cgi-bin/version.sh',
			'name' => 'Maxima',
			'program url' => 'https://maxima.sourceforge.io/',
			'params' => [ 'decorate' => false, 'showinput' => false ],
			'input' => 'code',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'preprocess' => __CLASS__ . '::decorateMaxima',
			'postprocess' => __CLASS__ . '::stripSlashedLineBreaks',
			'tag' => 'maxima'
		],

		'lilypond' => [
			'url' => 'http://lilypond/cgi-bin/cgi.sh?size=$size$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://lilypond/cgi-bin/version.sh',
			'name' => 'LilyPond',
			'program url' => 'http://lilypond.org/',
			'params' => [ 'size' => 'a4' ],
			'param filters' => [ 'size' => '/^\w+$/' ],
			'input' => 'score',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'score'
		],

		'zint' => [
			'url' => 'http://zint/cgi-bin/cgi.sh?type=$type$&eci=$eci$&data=$barcode_data$'
				. '&fg=$foreground$&bg=$background$&rotate=$rotate$&scale=$scale$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://zint/cgi-bin/version.sh',
			'name' => 'Zint',
			'program url' => 'https://www.zint.org.uk',
			'params' => [
				'type' => 'ISBNX',
				'barcode_data',
				'eci' => __CLASS__ . '::eci',
				'background' => '00000000',
				'foreground' => '000000',
				'rotate' => '0',
				'scale' => '1.0'
			],
			'param filters' => [
				'type' => '/^(CODE11|C25STANDARD|C25INTER|C25IATA|C25LOGIC|C25IND|CODE39|EXCODE39|EANX|EANX_CHK|'
					. 'GS1_128|CODABAR|CODE128|DPLEIT|DPIDENT|CODE16K|CODE49|CODE93|FLAT|DBAR_OMN|DBAR_LTD|DBAR_EXP|'
					. 'TELEPEN|UPCA|UPCA_CHK|UPCE|UPCE_CHK|POSTNET|MSI_PLESSEY|FIM|LOGMARS|PHARMA|PZN|PHARMA_TWO|'
					. 'PDF417|PDF417COMP|MAXICODE|QRCODE|CODE128B|AUSPOST|AUSREPLY|AUSROUTE|AUSDIRECT|ISBNX|RM4SCC|'
					. 'DATAMATRIX|EAN14|VIN|CODABLOCKF|NVE18|JAPANPOST|KOREAPOST|DBAR_STK|DBAR_OMNSTK|DBAR_EXPSTK|'
					. 'PLANET|MICROPDF417|USPS_IMAIL|PLESSEY|TELEPEN_NUM|ITF14|KIX|AZTEC|DAFT|DPD|MICROQR|HIBC_128|'
					. 'HIBC_39|HIBC_DM|HIBC_QR|HIBC_PDF|HIBC_MICPDF|HIBC_BLOCKF|HIBC_AZTEC|DOTCODE|HANXIN|MAILMARK|'
					. 'AZRUNE|CODE32|EANX_CC|GS1_128_CC|DBAR_OMN_CC|DBAR_LTD_CC|DBAR_EXP_CC|UPCA_CC|UPCE_CC|'
					. 'DBAR_STK_CC|DBAR_OMNSTK_CC|DBAR_EXPSTK_CC|CHANNEL|CODEONE|GRIDMATRIX|UPNQR|ULTRA|RMQR)$/i',
				'background' => '/^([0-9a-f]{6}|[0-9a-f]{8})$/i',
				'foreground' => '/^([0-9a-f]{6}|[0-9a-f]{8})$/i',
				'rotate' => '/^(0|90|180|270)$/',
				'scale' => __CLASS__ . '::isBetween0and100'
			],
			'input' => 'barcode_data',
			'postprocess' => __CLASS__ . '::innerXml',
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'barcode'
		],

		'graphviz' => [
			'url' => 'http://graphviz/cgi-bin/cgi.sh?layout=$layout$',
			'format' => 'text',
			'options' => [ 'sslVerifyCert' => false ],
			'version url' => 'http://graphviz/cgi-bin/version.sh',
			'name' => 'GraphViz',
			'program url' => 'https://graphviz.org/',
			'params' => [ 'layout' => 'dot' ],
			'param filters' => [ 'layout' => '/^(dot|neato|twopi|circo|fdp|osage|patchwork|sfdp)$/' ],
			'input' => 'dot',
			// Set this parameter to the path at which all uploads ($wgUploadDirectory) are mounted
			// to the graphviz container, assuming that the uploads for a single wiki are mounted
			// as 'mounted farm uploads/$wgDbName'.
			// If there is only one wiki in the farm, leave this parameter null and mount $wgUploadDirectory
			//  to graphviz as $wgUploadDirectory.
			// This is needed for the correct handling of locally uploaded images in graphviz diagrams.
			'mounted farm uploads' => null,
			'preprocess' => __CLASS__ . '::wikilinks4dot',
			'postprocess' => [
				__CLASS__ . '::innerXml',
				__CLASS__ . '::filepathToUrl'
			],
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'graphviz',
		],

		// mscgen:
		'mscgen' => [
			'url' => 'http://mscgen/cgi-bin/cgi.sh',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://mscgen/cgi-bin/version.sh',
			'name' => 'mscgen',
			'program url' => 'https://www.mcternan.me.uk/mscgen/',
			'input' => 'dot',
			'preprocess' => __CLASS__ . '::wikilinks4dot',
			'postprocess' => __CLASS__ . '::innerXml',
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'mscgen'
		],

		'plantuml' => [
			'url' => 'http://plantuml/cgi-bin/cgi.sh',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://plantuml/cgi-bin/version.sh',
			'name' => 'PlantUML',
			'program url' => 'https://plantuml.com',
			'params' => [ 'uml' ],
			'input' => 'uml',
			'preprocess' => __CLASS__ . '::wikilinks4uml',
			'postprocess' => __CLASS__ . '::innerXml',
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'plantuml'
		],

		'ploticus' => [
			'url' => 'http://ploticus/cgi-bin/cgi.sh?title=$title$&fontsize=$fontsize$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://ploticus/cgi-bin/version.sh',
			'name' => 'ploticus',
			'program url' => 'http://ploticus.sourceforge.net/doc/welcome.html',
			'params' => [ 'fontsize' => 8, 'title' => null ],
			'param filters' => [ 'fontsize' => '/^\d+(\.\d+)?$/' ],
			'input' => 'script',
			'postprocess' => [
				__CLASS__ . '::unmaskWikilinks',
				__CLASS__ . '::wikilinksInSvg',
				__CLASS__ . '::jsLinksInSvg'
			],
			'scripts' => '/js/ploticus',
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tries' => 1,
			'tag' => 'ploticus'
		],

		'gnuplot' => [
			'url' =>
				'http://gnuplot/cgi-bin/cgi.sh?width=$width$&height=$height$&size=$size$&name=$name$&heads=$heads$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://gnuplot/cgi-bin/version.sh',
			'name' => 'gnuplot',
			'program url' => 'http://www.gnuplot.info/',
			'params' => [ 'width' => 800, 'height' => 600, 'size' => 10, 'name' => 'gnuplot', 'heads' => 'butt' ],
			'param filters' => [
				'width' => '/^\d+$/',
				'height' => '/^\d+$/',
				'size' => '/^\d+$/',
				'heads' => '/^(rounded|butt|square)$/'
			],
			'input' => 'script',
			'postprocess' => [
				__CLASS__ . '::innerXml',
				__CLASS__ . '::sizeSVG'
			],
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'gnuplot'
		],

		'asymptote' => [
			'url' =>
				'http://asymptote/cgi-bin/cgi.sh?output=$output$',
			'options' => [ 'sslVerifyCert' => false, 'timeout' => 60 ],
			'format' => 'text',
			'version url' => 'http://asymptote/cgi-bin/version.sh',
			'name' => 'asymptote',
			'program url' => 'https://asymptote.sourceforge.io/',
			'params' => [ 'output' => 'svg', 'width' => 600, 'height' => 600 ],
			'param filters' => [ 'output' => '/^(svg|html)$/', 'width' => '/^\d+$/', 'height' => '/^\d+$/' ],
			'input' => 'script',
			'postprocess' => [
				__CLASS__ . '::innerXml',
				__CLASS__ . '::sizeSVG',
				__CLASS__ . '::wrapHtml'
			],
			'scripts' => '/js/asymptote/asygl-1.02.js',
			'original script' => 'https://vectorgraphics.github.io/asymptote/base/webgl/asygl-1.02.js',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'asy'
		],

		'vega' => [
			'url' => 'http://vega/cgi-bin/cgi.sh?width=$width$&height=$height$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://vega/cgi-bin/version.sh',
			'name' => 'Vega',
			'program url' => 'https://vega.github.io',
			'params' => [
				'json',
				'width' => 600,
				'height' => 600,
				'currency' => '₽',
				'yaml' => false,
				'id' => __CLASS__ . '::chartId'
			],
			'param filters' => [
				'json' => __CLASS__ . '::validateJsonOrYaml',
				'width' => '/^\d+$/', 'height' => '/^\d+$/',
				'currency' => '/^(\p{Sc}|[A-Z]{3})$/u',
			],
			'input' => 'json',
			'preprocess' => [
				__CLASS__ . '::yamlToJson',
				__CLASS__ . '::inject3d',
				__CLASS__ . '::remove160'
			],
			'postprocess' => __CLASS__ . '::animateChart',
			'scripts' => [
				'/js/vega/vega/build/vega.min.js',
				'/js/vega/vega-lite/build/vega-lite.min.js',
				'/js/vega/vega-embed/build/vega-embed.min.js'
			],
			'class' => 'vega',
			'html' => <<<'HTML'
				<div class="%1$s" id="%2$s">%4$s</div>
				%5$s
				HTML,
			'javascript' => <<<'JS'
				vegaEmbed( '#%1$s', %2$s ).then(
					function( result ) {
						console.log( 'vegaEmbed result: ' + result );
					} ).catch( function( error ) {
						mw.log.error( error );
					} );
			JS,
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'graph'
		],

		'mermaid' => [
			'url' => 'http://mermaid/cgi-bin/cgi.sh?id=$id$&scale=$scale$&width=$width$&height=$height$'
				. '&theme=$theme$&look=$look$&background=$background$',
			'options' => [ 'sslVerifyCert' => false ],
			'format' => 'text',
			'version url' => 'http://mermaid/cgi-bin/version.sh',
			'name' => 'mermaid', // need fallback to data source in version report.
			'program url' => 'https://mermaid-js.github.io',
			'params' => [
				'mmd',
				'scale' => 1,
				'width' => '800',
				'height' => '600',
				'theme' => 'default',
				'look' => 'classic',
				'background' => 'white',
				'id' => __CLASS__ . '::chartId'
			],
			'param filters' => [
				'scale' => '/^\d+(\.\d+)?$/',
				'width' => '/^\d+$/',
				'height' => '/^\d+$/',
				'theme' => '/^(default|forest|dark|neutral)$/i',
				'look' => '/^(classic|handDrawn)$/i',
				'background' => '/^(\w+|\#[0-9A-F]{6})$/i'
			],
			'input' => 'mmd',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'mermaid',
			'preprocess' => [
				__CLASS__ . '::screenColons',
				__CLASS__ . '::wikilinks4mermaid',
				__CLASS__ . '::injectMermaidParams'
			],
			'postprocess' => [
				__CLASS__ . '::onlySvg',
				__CLASS__ . '::wikiLinksInXml',
				__CLASS__ . '::animateChart'
			],
			'class' => 'mermaid',
			'html' => <<<'HTML'
				<div class="%1$s" id="%2$s" style="display: none;">%3$s</div>
				%4$s
				%5$s
				HTML,
			'javascript' => <<<'JS'
				mermaid.initialize({ startOnLoad: false, securityLevel: 'antiscript' });
				let source = document.getElementById( '%1$s' );
				source.style.display = 'block';
				mermaid.run({ nodes: [ source ] }); // convert Mermaid to diagram.
				document.getElementById( '%1$s_svg' ).remove(); // remove SVG fallback.
			JS,
			'scripts' => '/js/mermaid/mermaid.min.js'
		],

		'bpmn' => [
			'url' => 'http://bpmn:8080/',
			'options' => [ 'sslVerifyCert' => false, 'headers' => [ 'Content-Type' => 'application/xml' ] ],
			'format' => 'text',
			'version' => 'bpmn2svg by Pierre Schwang',
			'name' => 'bpmn2svg',
			'program url' => 'https://github.com/PierreSchwang/bpmn2svg',
			'params' => [ 'bpmn', 'width' => 400, 'height' => 300, 'scale' => 1, 'title' => 'BPMN diagram' ],
			'param filters' => [
				'bpmn' => __CLASS__ . '::validateXml',
				'width' => '/^\d+$/',
				'height' => '/^\d+$/',
				'scale' => '/^\d+(\.\d+)?$/'
			],
			'input' => 'bpmn',
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'bpmn'
		],
	];

	/**
	 * Return External Data sources, some of which cannot be constants.
	 *
	 * @return array[]
	 */
	public static function sources(): array {
		global $wgArticlePath, $wgLanguageCode;
		return self::SOURCES + [
			'timeline' => [
				'url' => 'http://easytimeline/cgi-bin/cgi.sh?path=' . $wgArticlePath,
				'options' => [ 'sslVerifyCert' => false ],
				'format' => 'text',
				'version url' => 'http://easytimeline/cgi-bin/version.sh',
				'name' => 'EasyTimeline',
				'program url' => 'http://infodisiac.com/Wikipedia/EasyTimeline/Introduction.htm',
				'params' => [ 'path' => $wgArticlePath ],
				'param filters' => [ 'path' => '/^' . preg_quote( $wgArticlePath, '/' ) . '$/' ],
				'input' => 'script',
				'scripts' => '/js/ploticus',
				'preprocess' => __CLASS__ . '::maskWikilinks',
				'postprocess' => [
					__CLASS__ . '::unmaskWikilinks',
					__CLASS__ . '::htmlEntityDecode',
					__CLASS__ . '::wikilinksInSvg'
				],
				'min cache seconds' => 30 * 24 * 60 * 60,
				'max tries' => 1,
				'tag' => 'timeline'
			],

			'echarts' => [
				'name' => 'Apache ECharts',
				'program url' => 'https://echarts.apache.org',
				'url' => 'http://echarts/cgi-bin/cgi.sh?width=$width$&height=$height$&theme=$theme$&locale=$locale$',
				'format' => 'text',
				'version url' => 'http://echarts/cgi-bin/version.sh',
				'params' => [
					'json',
					'yaml' => false,
					'width' => 400,
					'height' => 300,
					'locale' => $wgLanguageCode,
					'theme' => 'macarons',
					'id' => __CLASS__ . '::chartId'
				],
				'param filters' => [
					'json' => __CLASS__ . '::validateJsonOrYaml',
					'width' => '/^(\d+|auto)$/',
					'height' => '/^(\d+|auto)$/',
					'locale' => '/^(' . implode( '|', array_keys( Names::$names ) ) . ')$/',
					'theme' => '/^(azul|bee-inspired|blue|caravan|carp|cool|dark-blue|dark-bold|dark-digerati|'
						. 'dark-fresh-cut|dark-mushroom|dark|eduardo|forest|fresh-cut|fruit|gray|green|helianthus|'
						. 'infographic|inspired|jazz|london|macarons|macarons2|mint|packageon|red-velvet|red|roma|'
						. 'royal|sakura|shine|tech-blue|vintage)$/'
				],
				'input' => 'json',
				'preprocess' => __CLASS__ . '::yamlToJson',
				'postprocess' => __CLASS__ . '::animateEcharts',
				'class' => 'echarts',
				'html' => <<<'HTML'
					<div class="%1$s" id="%2$s">%4$s</div>%5$s
				HTML,
				'javascript' => <<<'JS'
					const init = %3$s;
					echarts.init( document.getElementById( '%1$s' ), init.theme, init ).setOption( %2$s );
				JS,
				'scripts' => '/js/echarts/dist/echarts.js',
				'max tries' => 1,
				'min cache seconds' => 30 * 24 * 60 * 60,
				'tag' => 'echarts'
			]
		];
	}

	/*
	 * Pre- and postprocessing utilities.
	 */

	/**
	 * Surround TeX with \(…\) or $$…$$ for MathJax.
	 *
	 * @param string $tex
	 * @param array $params
	 * @return string
	 */
	public static function encloseTex( string $tex, array $params ): string {
		return $params['display'] === 'block' ? '$$' . $tex . '$$' : "\($tex\)";
	}

	/** @const string[] ECI_AWARE ECI-aware types of bar / QR codes. */
	private const ECI_AWARE = [
		'AZTEC', 'DOTCODE', 'MAXICODE', 'QRCODE', 'CODEONE', 'GRIDMATRIX', 'MICROPDF417',
		'RMQR', 'DATAMATRIX', 'HANXIN', 'PDF417', 'ULTRA'
	];

	public static function eci( array $params ): string {
		return in_array( $params['type'], self::ECI_AWARE ) ? urlencode( '--eci=26' ) : '';
	}

	/**
	 * Return true if $scale is numeric and between 0 and 100.
	 *
	 * @param mixed $scale
	 * @return bool
	 */
	public static function isBetween0and100( $scale ): bool {
		return is_numeric( $scale ) && (float)$scale > 0 && (float)$scale <= 100;
	}

	/**
	 * A sevice function converting page title into its local URL.
	 * @param string|null $page
	 * @return string
	 */
	private static function localurl( ?string $page ): string {
		$parser = MediaWikiServices::getInstance()->getParser();
		$url = CoreParserFunctions::localurl( $parser, $page );
		return is_string( $url ) ? $url : '/';
	}

	/**
	 * Convert [[wikilinks]] to UML links.
	 *
	 * @param string $uml Text to add wikilinks in UML format.
	 * @return string dot with links.
	 */
	public static function wikilinks4uml( string $uml ): string {
		return preg_replace_callback( '/\[\[([^|\]]+)(?:\|([^]]*))?]]/', static function ( array $m ): string {
			return '[[' . self::localurl( $m[1] ) . ' ' . ( $m[2] ?? $m[1] ) . ']]';
		}, $uml );
	}

	/**
	 * Make an identifier for a <div> containing a Mermaid, ECharts or Vega diagram.
	 *
	 * @param array $params
	 * @return string
	 */
	public static function chartId( array $params ): string {
		return $params['source'] . '_' . hash( 'fnv1a64', var_export( $params, true ) );
	}

	/**
	 * Combating MediaWiki injecting &#160; in some places.
	 * @param string $json Vega config as JSON.
	 * @return string
	 */
	public static function remove160( string $json ): string {
		return preg_replace( '/(["\'}\w])\s+([:!?])/', '$1$2', $json );
	}

	/**
	 * Inject into Maxima commands sequence commands that cause formulae to be output as TeX and graphs, as SVG.
	 * @param string $maxima
	 * @param array $params
	 * @return string
	 */
	public static function decorateMaxima( string $maxima, array $params ) {
		if ( $params['decorate'] === false ) {
			return $maxima;
		}
		$input = $params['showinput'] !== false ? ' grind(_)$' : '';

		$inject = [
			'draw' => '$1 ($2, terminal = svg, file_name = "$file")',
			'draw2d' => '$1 ($2, terminal = svg, file_name = "$file")',
			'draw3d' => '$1 ($2, terminal = svg, file_name = "$file")',
			'plot2d' => '$1 ($2, [svg_file, "$file.svg"])',
			'plot3d' => '$1 ($2, [svg_file, "$file.svg"])',
			'julia' => '$1 ($2, [svg_file, "$file.svg"])',
			'mandelbot' => '$1 ($2, [svg_file, "$file.svg"])',
			'printfile' => '$0'
		];
		$notex_regex = '/(' . implode( '|', array_keys( $inject ) ) . ')\s*\((.+)\)\s*([$;]?)/s';

		if ( preg_match_all( '/((?:"[^"]*"|.)+?)([;$])/s', $maxima, $matches, PREG_SET_ORDER ) ) {
			$lines = [];
			foreach ( $matches as [ $_, $command, $suffix ] ) {
				$command = trim( $command );
				if ( preg_match( $notex_regex, $command, $matches2 ) ) {
					// We need to make it deterministic, in order not to kill the ED cache.
					$file = '/tmp/of' . md5( $command );
					$replace = str_replace( '$file', $file, $inject[$matches2[1]] );
					$command = preg_replace( $notex_regex, $replace, $command );
					$suffix = '$' . $input . ' ?sleep(1)$ printfile ("' . $file . '.svg")$';
				} else {
					if ( $suffix !== '$' ) {
						$shift = $input ? 1 : 0;
						$suffix = '$' . $input . ' '
							. 'print (tex (%th(' . ( 1 + $shift ) . '), false))$ '
							. '%th(' . ( 2 + $shift ) . ')$';
					}
				}
				$lines[] = $command . $suffix;
			}
			return implode( PHP_EOL, $lines );
		}
		return $maxima;
	}

	/**
	 * Strips TeX newline continuations with slashes, produced by Maxima and breaking operators.
	 * @param string $tex
	 * @return string
	 */
	public static function stripSlashedLineBreaks( string $tex ): string {
		return strtr( $tex, [ '\\' . PHP_EOL => '' ] );
	}

	/**
	 * Convert [[wikilinks]] to links in mermaid.
	 *
	 * @param string $mmd Text to add wikilinks in mermaid format.
	 * @return string dot with links.
	 */
	public static function wikilinks4mermaid( string $mmd ): string {
		return preg_replace_callback( '/\[\[([^|\]]+)(?:\|([^]]*))?]]/', static function ( array $m ): string {
			return sprintf( '<a href="%s">%s</a>', self::localurl( $m[1] ), $m[2] ?? $m[1] );
		}, $mmd );
	}

	/**
	 * Injects parameters for a Mermaid diagram.
	 *
	 * @param string $mmd Mermaid diagram code.
	 * @param array $params Tag parameters.
	 * @return string
	 */
	public static function injectMermaidParams( string $mmd, array $params ): string {
		$init = [];
		foreach ( [ 'width', 'height', 'theme', 'background', 'look' ] as $param ) {
			if ( isset( $params[$param] ) ) {
				$init[] = "'$param':'{$params[$param]}'";
			}
		}
		return '%%{init: {' . implode( ',', $init ) . "}}%%\n"
			. preg_replace( '/' . preg_quote( self::PLACEHOLDER, '/' ) . '/', urlencode( ':' ), $mmd );
	}

	/**
	 * @param string $script
	 * @return string
	 */
	public static function maskWikilinks( string $script ): string {
		return preg_replace( '/\[\[(.+?)]]/', '(startwikilink($1)endwikilink)', $script );
	}

	/**
	 * @param string $masked
	 * @return string
	 */
	public static function unmaskWikilinks( string $masked ): string {
		return preg_replace( [ '/\(startwikilink\(/', '/\)endwikilink\)/' ], [ '[[', ']]' ], $masked );
	}

	/** @var array $communicate Data to be passed from the preprocessor to the postprocesor. */
	private static $communicate = [];

	/**
	 * Convert [[wikilinks]] to dot links, including images and CSS.
	 *
	 * @param string $dot Text to add wikilinks in dot format.
	 * @param array $params Tag params.
	 * @return string dot with links.
	 */
	public static function wikilinks4dot( string $dot, array $params ): string {
		// Process URL = "[[wikilink]]" in properties.
		$attrs = implode( '|', [
			'edgehref', 'edgeURL', 'headhref', 'headURL', 'labelhref', 'labelURL', 'tailhref', 'tailURL', 'href', 'URL'
		] );
		$dewikified = preg_replace_callback(
			'/(?<attr>' . $attrs . ')\s*=\s*"\[\[(?<page>[^|<>\]"]+)]]"/',
			static function ( array $m ): string {
				return $m['attr'] . '="' . self::localurl( $m['page'] ) . '"';
			},
			$dot
		);
		// Process image or shapefile = "[[File:somefile.png|150px]]" in properties.
		$attrs = implode( '|', [ 'image', 'shapefile', 'src' ] );
		$repo = MediaWikiServices::getInstance()->getRepoGroup();
		$farm = $params['mounted farm uploads'] ?? null;
		$dewikified = preg_replace_callback(
			'/(?<attr>' . $attrs . ')\s*=\s*"\[\[[^:|\]]+:(?<image>[^<>\]"]+)]]"/i',
			static function ( array $m ) use ( $repo, $farm ) {
				$args = array_map( 'trim', explode( '|', $m['image'] ) );
				$name = array_shift( $args );
				$file = $repo->findFile( $name );
				$path = false;
				$url = false;
				if ( $file ) {
					$options = [];
					foreach ( $args as $arg ) {
						if ( strpos( $arg, '=' ) !== false ) {
							[ $key, $val ] = array_map( 'trim', explode( '=', $arg, 2 ) );
						} else {
							$key = isset( $options['width'] ) ? 'height' : 'width'; // first is width, second is height.
							$val = trim( $arg );
						}
						$options[$key] = (int)$val;
					}
					global $wgDefaultUserOptions, $wgThumbLimits;
					// @phan-suppress-next-line PhanPluginDuplicateExpressionAssignmentOperation Until dropping PHP 7.3.
					$options['width'] = $options['width'] ?? $wgThumbLimits[$wgDefaultUserOptions['thumbsize']];
					$thumb = $file->transform( $options );
					$path = $thumb->getLocalCopyPath();
					// Handle graphviz container's serving several wikis.
					global $wgUploadDirectory, $wgDBname;
					if ( $farm ) {
						$path = str_replace( $wgUploadDirectory, "$farm/$wgDBname", $path );
					}
					$url = $thumb->getUrl();
				}
				// If there is no local file, feed GraphViz something so that it does not break.
				// phpcs:ignore
				global $IP;
				$path = $path ?: "$IP/resources/assets/mediawiki.png";
				$url = $url ?: "/resources/assets/mediawiki.png";
				// @phan-suppress-next-line PhanPluginDuplicateExpressionAssignmentOperation Until dropping PHP 7.3.
				self::$communicate['urls'] = self::$communicate['urls'] ?? [];
				self::$communicate['urls'][$path] = $url;
				return $m['attr'] . '="' . $path . '"';
			},
			$dewikified
		);
		// Process [[wikilink]] in nodes.
		$dewikified = preg_replace_callback(
			'/\[\[(?<page>[^|<>\]]+)(\|(?<alias>[^<>\]]+))?]]\s*(?:\[(?<props>[^][]+)])?/',
			static function ( array $m ) {
				$props = $m['props'] ?? '';
				return '{"' . $m['page'] . '"['
					. 'URL="' . self::localurl( $m['page'] ) . '"; '
					. ( isset( $m['alias'] ) ? 'label="' . $m['alias'] . '";' : '' )
					. $props
					. ']}';
			},
			$dewikified
		);
		return $dewikified;
	}

	/**
	 * Return true, if file $name exists and its extension is '.pdf'.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function fileExistsAndIsPdf( string $name ): bool {
		$repo = MediaWikiServices::getInstance()->getRepoGroup();
		return $repo->findFile( $name ) && pathinfo( $name, PATHINFO_EXTENSION ) === 'pdf';
	}

	/**
	 * Convert HTML entities, that are unknown to XML, to characters.
	 *
	 * @param string $xml
	 * @return string
	 */
	public static function htmlEntityDecode( string $xml ): string {
		return html_entity_decode( $xml, ENT_NOQUOTES, 'UTF-8' );
	}

	/**
	 * Strip SVG from surrounding XML.
	 *
	 * @param string $xml XML to extract SVG from.
	 * @param array $params
	 * @return string The stripped SVG.
	 */
	public static function innerXml( string $xml, array $params ): string {
		if ( ( $params['output'] ?? '' ) === 'html' ) {
			return $xml;
		}
		$dom = new DOMDocument();
		$dom->loadXML( $xml, LIBXML_NOENT );
		return $dom->saveHTML( $dom->documentElement );
	}

	/**
	 * Strip an HTML tag from surrounding <html> and <body>.
	 *
	 * @param string $html HTML to extract the tag from.
	 * @return string The stripped SVG.
	 */
	public static function innerHtml( string $html ): string {
		// Resorting to the heinous art of parsing XML with regular expressions
		// as DOMDocument::loadHTML breaks HTML5 in some cases.
		// In particular, it dislikes minuses and primes in MathML.
		if ( preg_match( '%<body[^>]*>(.+)</body>%s', $html, $matches ) ) {
			return $matches[1];
		}
		return $html;
	}

	/**
	 * If $htm contains <html> tag, wrap it with <iframe>.
	 * @param string $html
	 * @param array $params
	 * @return string
	 */
	public static function wrapHtml( string $html, array $params ): string {
		if ( !preg_match( '~<html\s.+</html>~si', $html, $matches ) ) {
			return $html;
		}
		$html = str_replace( $params['original script'], $params['scripts'], $matches[0] );
		return '<iframe width="' . $params['width'] . '" height="' . $params['height'] . '" frameborder="0" srcdoc="'
			. strtr( $html, [ '&' => 'amp;', '"' => '&quot;' ] )
			. '"></iframe>';
	}

	/**
	 * Set SVG size, if not set.
	 *
	 * @param string $svg
	 * @param array $params
	 * @return string
	 */
	public static function sizeSVG( string $svg, array $params ): string {
		if ( ( $params['output'] ?? '' ) === 'html' ) {
			return $svg;
		}
		$dom = new DOMDocument();
		$dom->loadXML( $svg, LIBXML_NOENT );
		$root = $dom->documentElement;
		if ( !$root ) {
			return $svg;
		}
		foreach ( [ 'width', 'height' ] as $attr ) {
			if ( !$root->hasAttribute( $attr ) && ( $params[$attr] ?? 0 ) ) {
				$root->setAttribute( $attr, $params[$attr] );
			}
		}
		if ( !$root->hasAttribute( 'viewport' ) && isset( $params['width'] ) && isset( $params['height'] ) ) {
			$root->setAttribute( 'viewport', "0 0 {$params['width']} {$params['height']}" );
		}
		return $dom->saveHTML();
	}

	/**
	 * Convert [[…]] in SVG <text> into <a>.
	 *
	 * @param string $svg
	 * @return string
	 * @throws \MWException
	 */
	public static function wikilinksInSvg( string $svg ): string {
		$dom = new DOMDocument();
		self::throwWarnings();
		try {
			$loaded = $dom->loadXML( $svg, LIBXML_NOENT );
		} catch ( \Exception $e ) {
			throw new \MWException( 'Cannot process wikilinks in SVG: invalid XML (' . $e->getMessage() . ').' );
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$loaded ) {
			// SVG might be illegal and cannot be processed. Hopefully, this point is never reached.
			throw new \MWException( 'Cannot process wikilinks in SVG: invalid XML.' );
		}
		foreach ( $dom->getElementsByTagName( 'text' ) as $node ) {
			$text = $node->nodeValue;
			if ( preg_match_all(
				'/\[\[(?<page>[^]|]+)(?:\|(?<alias>[^]]+))?]]/u',
				$text,
				$matches,
				PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL
			) ) {
				$node->nodeValue = '';
				$position = 0;
				foreach ( $matches as $set ) {
					// Before the hyperlink.
					if ( $set[0][1] > $position ) {
						$node->appendChild(
							$dom->createTextNode( mb_substr( $text, $position, $set[0][1] - $position ) )
						);
					}
					// The hyperlink itself.
					$a = $dom->createElement( 'a', $set['alias'][0] ?? $set['page'][0] );
					$a->setAttribute( 'xlink:href', self::localurl( $set['page'][0] ) );
					$node->appendChild( $a );
					$position += strlen( $set[0][0] ) + 1;
				}
				// After the last hyperlink.
				if ( $position < strlen( $text ) ) {
					$node->appendChild( $dom->createTextNode( mb_substr( $text, $position ) ) );
				}
			}
		}

		return $dom->saveHTML();
	}

	/**
	 * Alter links to JavaScripts in SVG.
	 *
	 * @param string $svg
	 * @param array $params
	 * @return string
	 */
	public static function jsLinksInSvg( string $svg, array $params ): string {
		$dom = new DOMDocument();
		$dom->loadXML( $svg, LIBXML_NOWARNING );
		$root = $dom->documentElement;
		$attr = 'xlink:href';
		foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
			$path = $script->getAttribute( $attr );
			$script->setAttribute( $attr, "{$params['scripts']}/$path" );
		}
		return $dom->saveHTML( $root );
	}

	/**
	 * Replace local image paths with URLs in SVG.
	 *
	 * @param string $svg SVG to process
	 * @return string Prcessed SVG
	 * @throws \MWException
	 */
	public static function filepathToUrl( string $svg ): string {
		$dom = new DOMDocument();
		self::throwWarnings();
		try {
			$loaded = $dom->loadXML( preg_replace( '/(?<!<!)--(?!>)/', '—', $svg ) );
		} catch ( \Exception $e ) {
			throw new \MWException( 'Cannot process images in SVG: invalid XML (' . $e->getMessage() . ')' );
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$loaded ) {
			// Hopefully, this code will never be reached.
			throw new \MWException( 'Cannot process images in SVG: invalid XML).' );
		}
		$attr = 'xlink:href';
		foreach ( $dom->getElementsByTagName( 'image' ) as $image ) {
			$filepath = $image->getAttribute( $attr );
			$url = ( self::$communicate['urls'] ?? [] )[$filepath] ?? '';
			$image->setAttribute( $attr, $url );
		}
		return $dom->saveHTML();
	}

	/**
	 * Inject locale object for Vega.
	 *
	 * @param array|string $json
	 * @param array $params
	 * @return string
	 * @throws MWException
	 */
	public static function inject3d( $json, array $params ): string {
		$language = MediaWikiServices::getInstance()->getContentLanguage();
		$json = is_array( $json ) ? $json : json_decode( $json, true );
		$months = $language->getMonthNamesArray();
		$short_months = $language->getMonthAbbreviationsArray();
		$json['config'] = array_merge_recursive( $json['config'] ?? [], [
			'locale' => [
				'number' => [
					'decimal' => $language->separatorTransformTable()['.'],
					'thousands' => $language->separatorTransformTable()[','],
					'grouping' => [ NumberFormatter::GROUPING_SIZE ],
					'currency' => [ '', ( $params['params'] ?? [] )['currency'] ?? '$' ]
				]
			],
			'time' => [
				'datetime' => $language->getDateFormats()['mdy both'],
				'date' => $language->getDefaultDateFormat(),
				'time' => $language->getDateFormats()['mdy time'],
				'periods' => [ 'AM', 'PM' ],
				'days' => array_map( static function ( int $no ) use ( $language ): string {
					return $language->getWeekdayName( $no );
				}, range( 1, 7 ) ),
				'shortDays' => array_map( static function ( int $no ) use ( $language ): string {
					return $language->getWeekdayAbbreviation( $no );
				}, range( 1, 7 ) ),
				'months' => array_splice( $months, -12 ),
				'shortMonths' => array_splice( $short_months, -12 )
			]
		] );
		foreach ( [ 'width', 'height' ] as $param ) {
			if ( isset( $params[$param] ) ) {
				$json[$param] = $params[$param];
			}
		}
		return json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Make MathJax formula interactive.
	 *
	 * @param string $html MathML code wrapped in HTML.
	 * @param array $params Parameters passed to MathJax.
	 * @return string HTML code containing the animated MathJax.
	 */
	public static function addMathJaxMenu( string $html, array $params ): string {
		static $math_jax_included = false;
		if ( $params['nomenu'] === false && !$math_jax_included ) {
			$math_jax_included = true;
			$script = "\n" . '<script type="text/javascript" async src="'
				. "{$params['scripts']}/tex-mml-chtml.js" . '"></script>';
		} else {
			$script = '';
		}
		return "$html$script";
	}

	/** @const string PLACEHOLDER Temporary replacement for colons in wikilinks in Mermaid diagrams. */
	private const PLACEHOLDER = '(colon)';

	/**
	 * Screen colons in wikilinks in a Mermaid diagram.
	 *
	 * @param string $mmd
	 * @return string
	 */
	public static function screenColons( string $mmd ): string {
		return preg_replace_callback(
			'/\[\[[^]|]*(?:|[^]]+)?]]/u',
			static function ( array $captures ): string {
				return preg_replace( '/:/', self::PLACEHOLDER, $captures[0] );
			},
			$mmd
		);
	}

	/**
	 * Strips log messages before and after SVG that could not be stripped otherwise.
	 *
	 * @param string $input
	 * @return string
	 */
	public static function onlySvg( string $input ): string {
		return preg_match( '%<svg.+</svg>%i', $input, $matches ) ? $matches[0] : $input;
	}

	/**
	 * Convert wikilinks in XML to proper hyperlinks.
	 *
	 * @param string $xml
	 * @return string
	 */
	public static function wikiLinksInXml( string $xml ): string {
		$links = false;
		$colon = preg_quote( self::PLACEHOLDER, '/' );
		$xml = preg_replace_callback(
			'/\[\[(?<page>[^]|<>]+)(\|(?<alias>[^]<>]+))?]]/',
			static function ( array $matches ) use ( &$links, $colon ): string {
				$links = true;
				$attr = 'xlink:href';
				$page = preg_replace( "/$colon/", ':', $matches['page'] );
				$alias = preg_replace( "/$colon/", ':', $matches['alias'] ?? $page );
				return '<a ' . $attr . '="' . self::localurl( $page ) . '">' . $alias . '</a>';
			},
			$xml
		);
		// Add xmlns:xlink="http://www.w3.org/1999/xlink", if necessary:
		if ( $links ) {
			$xml = preg_replace( '/xmlns="[^"]+"/', '$0 xmlns:xlink="http://www.w3.org/1999/xlink"', $xml );
		}
		return $xml;
	}

	/**
	 * Animate a chart, making it interactive.
	 * @param string $svg Chart converted to SVG server-side.
	 * @param array $params Data source parameters.
	 * @param string $source Chart source code.
	 * @param string $init A string of chart parameters to be injected into JavaScript.
	 * @return string The resulting HTML code to inject.
	 */
	public static function animateChart(
		string $svg,
		array $params,
		string $source,
		string $init = ''
	): string {
		$promise = is_array( $params['scripts'] )
			? "$.when(\n" . implode( ",\n", array_map( static function ( string $script ): string {
				return "\tmw.loader.getScript( '$script' )";
			}, $params['scripts'] ) ) . "\n)"
			: "mw.loader.getScript( '{$params['scripts']}' )";
		$func = sprintf( $params['javascript'], $params['id'], $source, $init );
		$script = <<<SCRIPT
			<script type="text/javascript">
				(function() {
					let waitForJQuery = setInterval( function() {
						if ( typeof $!== 'undefined' ) { // do not insert space.
							if ( typeof mw.loader.getScript!== 'undefined' ) { // do not insert space.
								$promise.then(
									function() {
										$func
									},
									function( e ) {
										mw.log.error( e.message ); // => "Failed to load script"
									}
								);
								clearInterval( waitForJQuery );
							}
						}
					}, 10 );
				} )();
			</script>
		SCRIPT;
		return sprintf( $params['html'], $params['class'], $params['id'], $source, $svg, $script );
	}

	/**
	 * Make interactive ECharts visualisation based on the original JSON, with SVG fallback.
	 *
	 * @param string $svg ECharts visualisation exported to SVG to be used as fallback.
	 * @param array $params Parameters passed to ECharts engine, including the source JSON.
	 * @param string $json Preprocessed JSON for ECharts.
	 * @return string HTML code containing the animated ECharts with SVG fallback.
	 */
	public static function animateEcharts( string $svg, array $params, string $json ): string {
		return self::animateChart(
			$svg,
			$params,
			$json,
			json_encode( [
				'width' => $params['width'],
				'height' => $params['height'],
				'locale' => $params['locale'],
				'theme' => $params['theme'] ?? ''
			] )
		);
	}
}
