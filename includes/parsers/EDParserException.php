<?php
/**
 * Class for exceptions thrown by EDParser* methods and intercepted by EDConnectors* constructors.
 *
 * @author Alexander Mashin
 *
 */

class EDParserException extends Exception {
	/** @var string MW message code. */
	private $msgCode;
	/** @var array Parameters to that message. */
	private $params;

	/**
	 * Constructor.
	 *
	 * @param string $code MW message code.
	 * @param string ...$params Message parameters.
	 */
	public function __construct( $code, ...$params ) {
		parent::__construct( wfMessage( $code, $params )->inContentLanguage()->text() );
		$this->msgCode = $code;
		$this->params = $params;
	}

	/**
	 * Return MW message code.
	 *
	 * @return string Message code.
	 */
	public function code() {
		return $this->msgCode;
	}

	/**
	 * Return MW message params.
	 *
	 * @return array Message params.
	 */
	public function params() {
		return $this->params;
	}
}
