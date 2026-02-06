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
		 * This data source does not replace MathJax MW extension
		 * (https://github.com/alex-mashin/MathJax),
		 * not supporting automatic wikilinking and equation numbering.
		 */
		'mathjax' => self::DOCKER + [
			'url' => 'http://mathjax/cgi-bin/cgi.sh?config=yes',
			'version url' => 'http://mathjax/cgi-bin/version.sh',
			'name' => 'MathJax',
			'program url' => 'https://www.mathjax.org/',
			'params' => [ 'display' => 'inline', 'nomenu' => false ],
			'param filters' => [ 'display' => '/^(block|inline)$/' ],
			'input' => 'tex',
			'preprocess' => __CLASS__ . '::encloseTex',
			'postprocess' => [
				__CLASS__ . '::innerHtml',
				__CLASS__ . '::addMathJaxMenu'
			],
			'scripts' => '/js/mathjax',
			'tag' => 'mathjax',
		],

		'hevea' => self::DOCKER + [
			'url' => 'http://hevea/cgi-bin/cgi.sh',
			'version url' => 'http://hevea/cgi-bin/version.sh',
			'name' => 'Hevea',
			'program url' => 'http://hevea.inria.fr/',
			'params' => [ 'giac' => 'false' ],
			'input' => 'tex',
			'postprocess' => __CLASS__ . '::htmlBody',
			'scripts' => '/js/hevea',
			'tag' => 'latex'
		],

		'maxima' => self::DOCKER + [
			'url' => 'http://maxima/cgi-bin/cgi.sh',
			'version url' => 'http://maxima/cgi-bin/version.sh',
			'name' => 'Maxima',
			'program url' => 'https://maxima.sourceforge.io/',
			'params' => [ 'decorate' => false, 'showinput' => false ],
			'input' => 'code',
			'preprocess' => [ __CLASS__ . '::stripComments', __CLASS__ . '::decorateMaxima' ],
			'postprocess' => [ __CLASS__ . '::stripSlashedLineBreaks', __CLASS__ . '::doubleEol' ],
			'tag' => 'maxima'
		],

		'octave' => self::DOCKER + [
			'url' => 'http://octave/cgi-bin/cgi.sh?code=$code$',
			'version url' => 'http://octave/cgi-bin/version.sh',
			'name' => 'Octave',
			'program url' => 'https://octave.org/',
			'params' => [ 'code' => 'false' ],
			'input' => 'script',
			'postprocess' => __CLASS__ . '::htmlBody',
			'tag' => 'octave'
		],

		'cadabra' => self::DOCKER + [
			'url' => 'http://cadabra/cgi-bin/cgi.sh?code=$code$&cells=$cells$',
			'version url' => 'http://cadabra/cgi-bin/version.sh',
			'name' => 'Cadabra2',
			'program url' => 'https://cadabra.science/',
			'params' => [ 'json', 'yaml' => false, 'code' => 'false', 'cells' => 'false' ],
			'param filters' => [ 'json' => __CLASS__ . '::validateJsonOrYaml' ],
			'input' => 'json',
			'preprocess' => __CLASS__ . '::yamlToJson',
			'postprocess' => __CLASS__ . '::htmlBody',
			'tag' => 'cadabra'
		],

		'yacas' => self::DOCKER + [
			'url' => 'http://yacas/cgi-bin/cgi.sh',
			'version url' => 'http://yacas/cgi-bin/version.sh',
			'name' => 'Yacas',
			'program url' => 'https://yacas.org',
			'params' => [ 'decorate' => false ],
			'input' => 'script',
			'preprocess' => __CLASS__ . '::decorateYacas',
			'postprocess' => __CLASS__ . '::reWrapMaths',
			'tag' => 'yacas'
		],

		'gnuplot' => self::DOCKER + [
			'url' =>
				'http://gnuplot/cgi-bin/cgi.sh?&width=$width$&height=$height$&size=$size$&name=$name$&heads=$heads$',
			'version url' => 'http://gnuplot/cgi-bin/version.sh',
			'name' => 'gnuplot',
			'program url' => 'http://www.gnuplot.info/',
			'params' => [
				'width' => 800,
				'height' => 600,
				'size' => 10,
				'id' => __CLASS__ . '::numbered',
				'heads' => 'butt'
			],
			'param filters' => [
				'width' => '/^\d+$/',
				'height' => '/^\d+$/',
				'size' => '/^\d+$/',
				'heads' => '/^(rounded|butt|square)$/'
			],
			'input' => 'script',
			'tag' => 'gnuplot'
		],

		'asymptote' => self::DOCKER + [
			'url' => 'http://asymptote/cgi-bin/cgi.sh?output=$output$',
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
			'scripts' => '/js/asymptote/asygl.js',
			'original scripts' => '%https://vectorgraphics.github.io/asymptote/base/webgl/asygl-\d+\.\d+\.js%',
			'tag' => 'asy'
		],
	];

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
		$suffix = '[$;]';
		$label = '[^:\s]+?:';

		// Suppress access to filesystem:
		$dangerous = [
			'appendfile', 'batch', 'batchload', 'closefile', 'file_output_append', 'filename_merge',
			'file_search', 'file_search_cache', 'file_search_maxima', 'file_search_lisp', 'file_search_demo',
			'file_search_usage', 'file_search_tests', 'file_type', 'file_type_lisp', 'file_type_maxima',
			'gnuplot_command', 'load', 'load_pathname', 'loadfile', 'loadprint',
			'pathname_directory', 'pathname_name', 'pathname_type',
			'printfile', 'save', 'stringout', 'with_stdout', 'writefile'
		];
		$regex = self::commandRegex( $dangerous, $label, '', $suffix );
		$maxima = self::wrapCommands( $maxima, $regex, '' );

		// Wrap plotting commands.
		$drawers = [ 'draw', 'draw2d', 'draw3d' ];
		$plotters = [ 'gr2d', 'gr3d', 'plot2d', 'plot3d', 'contour_plot', 'julia', 'mandelbrot' ];
		// Wrap draw, draw2d, draw3d:
		$regex = self::commandRegex( $drawers, $label, 'wx', $suffix );
		$wrapper = '%1$s %2$s (%3$s, terminal = svg, file_name = "$file", '
			. 'user_preamble="set terminal svg mouse jsdir \'/js/gnuplot\' butt")'
			. '%4$s$' . $input . ' ?sleep(1)$ printfile ("$file.svg")$' . "\n";
		$maxima = self::wrapCommands( $maxima, $regex, $wrapper );
		// Wrap gr2d, gr3d, plot2d, plot3d, julia, mandelbrot:
		$regex = self::commandRegex( $plotters, $label, 'wx', $suffix );
		$wrapper = '%1$s %2$s (%3$s, [svg_file, "$file.svg"],'
			. '[gnuplot_preamble, "set terminal svg mouse jsdir \'/js/gnuplot\' butt"]'
			. ')%4$s$' . $input
			. ' ?sleep(1)$ printfile ("$file.svg")$' . "\n";
		$maxima = self::wrapCommands( $maxima, $regex, $wrapper );

		// Wrap formulæ ending with ;:
		$commands = self::commandRegex( array_merge( $drawers, $plotters ), $label, 'wx', ';', false );
		$shift = $input ? 1 : 0;
		$wrapper = '%1$s %2$s (%3$s)%4$s$' . $input . ' '
			. 'print (tex (%%th(' . ( 1 + $shift ) . '), false))$ '
			. '%%th(' . ( 2 + $shift ) . ')$' . "\n";
		$maxima = self::wrapCommands( $maxima, $commands, $wrapper );
		$maxima = preg_replace( '/\(\s*\)/su', '', $maxima );

		return $maxima;
	}

	/**
	 * Remove block comments.
	 * @param string $code
	 * @return string
	 */
	public static function stripComments( string $code ): string {
		return preg_replace( '~/\*.*?\*/~su', '', $code );
	}

	/**
	 * Generate a regular expression matching Maxima commands.
	 * @param array $commands
	 * @param string $label
	 * @param string $prefix
	 * @param string $suffix
	 * @param bool $affirm True, if a command myst be one of $commands, false, if it shouldn't.
	 * @param string $open
	 * @param string $close
	 * @return string
	 */
	private static function commandRegex(
		array $commands,
		string $label = '',
		string $prefix = '',
		string $suffix = ';',
		bool $affirm = true,
		string $open = '(',
		string $close = ')'
	) {
		$open = preg_quote( $open, '/' );
		$close = preg_quote( $close, '/' );
		$list = implode( '|', $commands );
		return "/(?<=^|$suffix)\s*(?<label>$label)?\s*"
			. ( $affirm
				? "(?<prefix>$prefix)?(?<command>$list)"
				: "(?!(?:$prefix)?$list)(?<prefix>$prefix)?(?<command>[^$close$open$suffix,\\s[\\]]+)"
			) . '\s*'
			. "(?<params>(?<parentheses>{$open}[^$close$open]*+(?:(?&parentheses)[^$close$open]*)*+$close))?\s*"
			. '(?<variables>(?:,\s*\w+\s*=.+?)+)?'
			. "(?<suffix>$suffix)/su";
	}

	/**
	 * Reformat commands using $format.
	 * @param string $commands
	 * @param string $command_regex
	 * @param string $format
	 * @return string
	 */
	private static function wrapCommands( string $commands, string $command_regex, string $format ): string {
		return preg_replace_callback( $command_regex, static function ( array $captures ) use ( $format ): string {
			$params = $captures['params'] ? substr( $captures['params'], 1, -1 ) : '<remove>';
			$wrapped = sprintf(
				$format,
				$captures['label'],
				$captures['command'],
				$params,
				$captures['variables'],
				$captures['suffix']
			);
			// Remove empty parentheses just introduced.
			$wrapped = preg_replace( '/\(\s*<remove>\s*\)/', '', $wrapped );
			// Inject tmp file name for SVGs, etv.
			if ( str_contains( $wrapped, '$file' ) ) {
				$file = '/tmp/of' . md5( $wrapped );
				$wrapped = str_replace( '$file', $file, $wrapped );
			}
			return $wrapped;
		}, $commands );
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
	 * Wraps Yacas code, except plotting functions, with Secure().
	 * @param string $yacas
	 * @return string
	 */
	public static function decorateYacas( string $yacas ): string {
		if ( !preg_match( '/;\s*$/su', $yacas ) ) {
			$yacas = "$yacas;";
		}
		$plotters = [ 'Plot2D', 'Plot3DS' ];

		// Remove potentially dangerous code.
		$regex = self::commandRegex( $plotters, '', '', ';', false );
		$yacas = self::wrapCommands( $yacas, $regex, 'Secure( %2$s( %3$s ) );' );

		// Wrap plotting functions.
		$regex = self::commandRegex( $plotters, '', '', ';' );
		$wrapper = '%2$s( %3$s, output = "svg", filename = "$file.svg" ); SystemCall( "cat $file.svg" );' . "\n";
		$yacas = self::wrapCommands( $yacas, $regex, $wrapper );

		return $yacas;
	}

	/**
	 * Double newlines.
	 * @param string $text
	 * @return string
	 */
	public static function doubleEol( string $text ): string {
		return str_replace( PHP_EOL, PHP_EOL . PHP_EOL, $text );
	}

	/**
	 * If you cannto afford wrapping inline maths with $...$, because it will interfere with Maxima, use this.
	 */
	public static function reWrapMaths( string $text ): string {
		return preg_replace( '/\$\s*([^$]+)\s*\$/', '\( $1 \)', $text );
	}
}
