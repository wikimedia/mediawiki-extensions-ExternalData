<?php

namespace ExternalData\Presets;

/**
 * Class with formula rendering, computer algebra and graph presets.
 *
 * @author Alexander Mashin
 */
class Math extends Base {
	/**
	 * @const array SOURCES Connections to Docker containers for testing purposes with useful multimedia programs.
	 * Use $wgExternalDataSources = array_merge( $wgExternalDataSources, Presets::test ); to make all of them available.
	 */
	public const SOURCES = [
		/*
		 * This data source does not replace MathJax MW extension (https://github.com/alex-mashin/MathJax),
		 * not supporting automatic wikilinking and equation numbering.
		 */
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

		'octave' => [
			'url' => 'http://octave/cgi-bin/cgi.sh?code=$code$',
			'format' => 'text',
			'options' => [ 'sslVerifyCert' => false, 'timeout' => 90 ],
			'version url' => 'http://octave/cgi-bin/version.sh',
			'name' => 'Octave',
			'program url' => 'https://octave.org/',
			'params' => [ 'code' => 'false' ],
			'input' => 'script',
			'postprocess' => __CLASS__ . '::htmlBody',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'octave'
		],

		'cadabra' => [
			'url' => 'http://cadabra/cgi-bin/cgi.sh?code=$code$',
			'format' => 'text',
			'options' => [ 'sslVerifyCert' => false, 'timeout' => 90 ],
			'version url' => 'http://cadabra2/cgi-bin/version.sh',
			'name' => 'Cadabra2',
			'program url' => 'https://cadabra.science/',
			'params' => [ 'json', 'yaml' => false, 'code' => 'false' ],
			'param filters' => [ 'json' => __CLASS__ . '::validateJsonOrYaml' ],
			'input' => 'json',
			'preprocess' => __CLASS__ . '::yamlToJson',
			'postprocess' => __CLASS__ . '::htmlBody',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'cadabra'
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
				__CLASS__ . '::onlySvg',
				__CLASS__ . '::sizeSvg'
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
				__CLASS__ . '::onlySvg',
				__CLASS__ . '::sizeSvg',
				__CLASS__ . '::wrapHtml'
			],
			'scripts' => '/js/asymptote/asygl-1.02.js',
			'original script' => 'https://vectorgraphics.github.io/asymptote/base/webgl/asygl-1.02.js',
			'max tries' => 1,
			'min cache seconds' => 30 * 24 * 60 * 60,
			'tag' => 'asy'
		],
	];

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
			'gr2d' => '$1 ($2, [svg_file, "$file.svg"])',
			'gr3d' => '$1 ($2, [svg_file, "$file.svg"])',
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
}
