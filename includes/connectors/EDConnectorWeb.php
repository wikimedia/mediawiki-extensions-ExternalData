<?php
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Class implementing {{#get_web_data:}} and mw.ext.externalData.getWebData.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */

class EDConnectorWeb extends EDConnectorHttp {

	/** @const int VERSION_TTL Number of seconds that software version is to be cached. */
	private const VERSION_TTL = 3600; // one hour.

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	public function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		$this->options['postData'] = $args['post data'] ?? $this->options['postData'] ?? $this->input ?? null;

		self::$method = $this->options['postData'] ? 'POST' : 'GET';
	}

	/**
	 * Fetch the web data. Sets HTTP headers.
	 *
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @return string|null Fetched text.
	 */
	protected function fetcher( $url, array $options ): ?string {
		// We do not want to repeat error messages self::$tries times.
		static $log_errors = true;
		[ $result, $this->headers, $errors ] = self::request(
			static::$method,
			$url,
			$options,
			$this->suppressError(),
			__METHOD__
		);
		if ( $errors && $log_errors ) {
			$this->error( 'externaldata-url-not-fetched', $this->originalUrl );
			foreach ( $errors as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) && isset( $error['params'] ) ) {
					$this->error( $error['message'], $error['params'] ); // -- MW message.
				} else {
					$this->error( 'externaldata-url-not-fetched', $this->originalUrl, (string)$error ); // -- string.
				}
			}
			$log_errors = false; // once is enough.
		}
		return $result ? trim( $result ) : $result;
	}

	/**
	 * Return the version of the relevant software to be used at Special:Version.
	 * @param array $config
	 * @return array [ 'name', 'version' ]
	 */
	public static function version( array $config ): array {
		[ $name, $version ] = parent::version( $config );
		if ( !( $name && $version ) && isset( $config['version url'] ) ) {
			// Version is supplied by the container.
			$version_url = self::realUrl( $config['version url'], $config['replacements'] ?? null );
			$fname = __METHOD__;
			$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
			$version = $cache->getWithSetCallback(
				$cache->makeGlobalKey( __CLASS__, $version_url ),
				self::VERSION_TTL,
				static function () use ( $version_url, $fname ): ?string {
					$factory = MediaWikiServices::getInstance()->getHttpRequestFactory();
					$req = $factory->create( $version_url, [], $fname );
					return $req->execute()->isOK() ? trim( $req->getContent() ) : null;
				}
			);
		}
		return [ $name, $version ];
	}
}
