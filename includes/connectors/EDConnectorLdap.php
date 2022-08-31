<?php
/**
 * Class implementing {{#get_ldap_data:}} and mw.ext.externalData.getLdapData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorLdap extends EDConnectorBase {
	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'domain';

	/** @var string LDAP filter. */
	private $filter;
	/** @var bool Get all LDAP data. */
	private $all;
	/** @var string LDAP domain (key to $wgExternalDataSources, etc.). */
	private $domain;
	/** @var string Base DN for the directory. */
	private $baseDn;
	/** @var string Real LDAP server. */
	private $server;
	/** @var string Real LDAP user. */
	private $user;
	/** @var string Real LDAP password. */
	private $password;
	/** @var resource|null Connection to LDAP server. */
	private $connection;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		$this->domain = isset( $args[self::ID_PARAM] ) ? $args[self::ID_PARAM] : null;

		// This connector needs an explicit set of fields.
		if ( !array_key_exists( 'data', $args ) ) {
			$this->error( 'externaldata-no-param-specified', 'data' );
		}

		// Parameters specific for {{#get_ldap_data:}} and mw.ext.externalData.getLdapData.
		if ( !function_exists( 'ldap_connect' ) ) {
			$this->error(
				'externaldata-missing-library',
				'php-ldap',
				'#get_ldap_data',
				'mw.ext.externalData.getLdapData'
			);
		}
		if ( isset( $args['filter'] ) ) {
			$this->filter = $args['filter'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'filter' );
		}
		if ( isset( $args['server'] ) ) {
			$this->server = $args['server'];
		} else {
			$this->error( 'externaldata-ldap-domain-not-defined', $this->domain );
		}
		$this->user = isset( $args['user'] ) ? $args['user'] : null;
		$this->password = isset( $args['password'] ) ? $args['password'] : null;
		if ( isset( $args['base dn'] ) ) {
			$this->baseDn = $args['base dn'];
		} else {
			$this->error( 'externaldata-ldap-domain-not-defined', $this->domain );
		}
		$this->all = array_key_exists( 'all', $args ) && $args['all'] !== false;
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		$this->connectLDAP();
		if ( !$this->connection ) {
			return false;
		}
		$external_values = $this->searchLDAP();
		$result = [];
		foreach ( $external_values as $i => $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}
			foreach ( $this->mappings() as $external_var ) {
				if ( !array_key_exists( $external_var, $result ) ) {
					$result[$external_var] = [];
				}
				if ( array_key_exists( $external_var, $row ) ) {
					if ( $this->all ) {
						foreach ( $row[$external_var] as $j => $value ) {
							if ( $j !== 'count' ) {
								$result[$external_var][] = $value;
							}
						}
					} else {
						$result[$external_var][] = $row[$external_var][0];
					}
				} else {
					$result[$external_var][] = '';
				}
			}
		}
		$this->add( $result );
		return true;
	}

	/**
	 * Connect to LDAP server using server, username and password set by the constructor.
	 * Set $this->credentials.
	 */
	private function connectLDAP() {
		$this->connection = ldap_connect( $this->server );
		if ( $this->connection ) {
			// these options for Active Directory only?
			ldap_set_option( $this->connection, LDAP_OPT_PROTOCOL_VERSION, 3 );
			ldap_set_option( $this->connection, LDAP_OPT_REFERRALS, 0 );
			$bound = false;
			$exception = false;
			// Throw exceptions instead of warnings.
			self::throwWarnings();
			try {
				$bound = $this->user
					   ? ldap_bind( $this->connection, $this->user, $this->password )
					   : ldap_bind( $this->connection ); // anonymously.
			} catch ( Exception $e ) {
				$this->error( 'externaldata-ldap-unable-to-bind', $this->domain );
				$this->connection = null;
				$exception = true;
			} finally {
				// Restore warnings.
				self::stopThrowingWarnings();
			}
			if ( !$bound && !$exception /* Do not repeat  twice. */ ) {
				$this->error( 'externaldata-ldap-unable-to-bind', $this->domain ); // not $this->server!
				$this->connection = null;
			}
		} else {
			$this->error( 'externaldata-ldap-unable-to-connect', $this->domain ); // not $this->server!
		}
	}

	/**
	 * Search LDAP.
	 *
	 * @return array Search results.
	 */
	private function searchLDAP() {
		$sr = ldap_search( $this->connection, $this->baseDn, $this->filter, array_values( $this->mappings() ) );
		$results = ldap_get_entries( $this->connection, $sr );
		return $results;
	}
}
