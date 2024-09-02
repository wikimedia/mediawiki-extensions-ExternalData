<?php

namespace ExternalData\Presets;

/**
 * Class wrapping the constant containing data source presets for testing purposes for autoloading.
 *
 * @author Alexander Mashin
 *
 */

class Test extends Base {
	/**
	 * @const array SOURCES Connections, mainly to Docker containers for testing purposes.
	 */
	public const SOURCES = [
		// An example LDAP data source for testing.
		'ldap' => [
			'server' => 'ldap.forumsys.com',
			'base dn' => 'dc=example,dc=com',
			'user' => 'uid=tesla,dc=example,dc=com',
			'password' => 'password'
		],
		// An example external mySQL data source.
		'rfam' => [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		],
		'rfam_prepared' => [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => '',
			'prepared' => <<<'SQL'
				SELECT fr.rfam_acc, fr.rfamseq_acc, fr.seq_start, fr.seq_end
				FROM full_region fr, rfamseq rf, taxonomy tx
				WHERE rf.ncbi_id = tx.ncbi_id
				AND fr.rfamseq_acc = rf.rfamseq_acc
				AND tx.ncbi_id = ?
				AND is_significant = 1 -- exclude low-scoring matches from the same clan
				LIMIT 20;
			SQL
		],
		'rfam_prepared_multiple' => [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => '',
			'prepared' => [
				'sequences' => <<<'SEQ'
					SELECT fr.rfam_acc, fr.rfamseq_acc, fr.seq_start, fr.seq_end
					FROM full_region fr, rfamseq rf, taxonomy tx
					WHERE rf.ncbi_id = tx.ncbi_id
					AND fr.rfamseq_acc = rf.rfamseq_acc
					AND tx.ncbi_id = ?
					AND is_significant = 1 -- exclude low-scoring matches from the same clan
					LIMIT 20;
				SEQ,
				'sno' => <<<'SNO'
					SELECT fr.rfam_acc, fr.rfamseq_acc, fr.seq_start, fr.seq_end, f.type
					FROM full_region fr, rfamseq rf, taxonomy tx, family f
					WHERE
					rf.ncbi_id = tx.ncbi_id
					AND f.rfam_acc = fr.rfam_acc
					AND fr.rfamseq_acc = rf.rfamseq_acc
					AND tx.tax_string LIKE ?
					AND f.type LIKE '%snoRNA%'
					AND is_significant = 1 -- exclude low-scoring matches from the same clan
					LIMIT 20;
				SNO
			]
		],
		// An example external mySQL data source with hidden parametres and prepared statements.
		'secret' => [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => '',
			'prepared' => <<<'SECRET'
				SELECT fr.rfam_acc AS acc, fr.rfamseq_acc AS seq_acc, fr.seq_start AS start, fr.seq_end AS end, f.type
				FROM full_region fr, rfamseq rf, taxonomy tx, family f
				WHERE
				rf.ncbi_id = tx.ncbi_id
				AND f.rfam_acc = fr.rfam_acc
				AND fr.rfamseq_acc = rf.rfamseq_acc
				AND tx.tax_string LIKE '%Mammalia%'
				AND f.type LIKE '%snoRNA%'
				AND is_significant = 1 -- exclude low-scoring matches from the same clan
				LIMIT 20;
			SECRET,
			'limit' => 25,
			'hidden' => true
		],
		// Connection to a test PostreSQL database 'dvdrental' in a container that should be called 'postgres'.
		'postgresql' => [
			'server' => 'postgresql',
			'type' => 'postgres',
			'name' => 'dvdrental',
			'user file' => '/run/secrets/postgresql_user',
			'password file' => '/run/secrets/postgresql_password',
		],
		// Connection to a test PostreSQL database 'dvdrental' with prepared statements.
		'postgresql_prepared' => [
			'server' => 'postgresql',
			'type' => 'postgres',
			'name' => 'dvdrental',
			'user file' => '/run/secrets/postgresql_user',
			'password file' => '/run/secrets/postgresql_password',
			'prepared' => <<<'POSTGRE1'
				SELECT title, description, length FROM film WHERE length < $1 ORDER BY length DESC LIMIT 25;
			POSTGRE1
		],
		// Connection to a test Microsoft SQL Server database 'Northwind' with running in the container 'mssqlserver'.
		'mssqlserver' => [
			'driver' => 'ODBC Driver 18 for SQL Server',
			'server' => 'mssqlserver,1433',
			'type' => 'odbc',
			'name' => 'Northwind',
			'user file' => '/run/secrets/mssqlserver_user',
			'password file' => '/run/secrets/mssqlserver_password',
			'trust server certificate' => true
		],
		// Connection to MSSQL 'Northwind' DB with running in the container 'mssqlserver' with prepared statements.
		'mssqlserver_prepared' => [
			'driver' => 'ODBC Driver 18 for SQL Server',
			'server' => 'mssqlserver,1433',
			'type' => 'odbc',
			'name' => 'Northwind',
			'user file' => '/run/secrets/mssqlserver_user',
			'password file' => '/run/secrets/mssqlserver_password',
			'trust server certificate' => true,
			'prepared' => <<<'ODBC'
				SELECT TOP 10 TitleOfCourtesy, FirstName, LastName, Title, City, COUNT(OrderID) AS NoOrders
				FROM Employees LEFT JOIN Orders ON Employees.EmployeeID=Orders.EmployeeID
				WHERE Title = ?
				GROUP BY TitleOfCourtesy, FirstName, LastName, Title, City
				ORDER BY NoOrders DESC;
			ODBC
		],
		// Connection to a test MongoDB database with US zip codes running in the container 'mongodb'.
		'mongodb' => [
			'type' => 'mongodb',
			'name' => 'test',
			'server' => 'mongodb:27017',
			'user' => 'wikiuser',
			'password file' => '/run/secrets/mongodb_password'
		],
		'file' => [
			'path' => __DIR__ . '/../../extension.json'
		],
		'directory' => [
			'path' => __DIR__ . '/../../includes',
			'depth' => 1
		]
	];

	/**
	 * Create External Data sources that cannot be constants.
	 * @return array[]
	 */
	public static function sources(): array {
		global $wgDBserver, $wgDBname, $wgDBuser, $wgDBpassword;
		return self::SOURCES + [
			// An example data source giving access to MW database with prepared statements.
			'local' => [
				'server' => $wgDBserver,
				'type' => 'mysql',
				'name' => $wgDBname,
				'user' => $wgDBuser,
				'password' => $wgDBpassword,
				'prepared' => [
					'external links' => <<<'EL'
						SELECT domain, links
						FROM (
						        SELECT
									SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(el_index_60,
						    			'/', 3),
						    			'://', -1),
						    			'/', 1),
						    			'?', 1
						    		) AS domain, COUNT(el_id) AS links
						        FROM externallinks
						        GROUP BY domain
						) AS grouped
						ORDER BY links DESC
						LIMIT ?
					EL,
					'interwiki' => [
						'query' => <<<'IW'
							SELECT domain AS Domain, links
							FROM (
							    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(iw_url,
							    	'/', 3),
							    	'://', -1),
							    	'/', 1),
							    	'?', 1
							    ) AS domain, COUNT(iwlinks.iwl_title) AS links
							    FROM iwlinks INNER JOIN interwiki ON iwlinks.iwl_prefix = interwiki.iw_prefix
							    GROUP by domain
							) AS grouped
							ORDER BY links DESC
							LIMIT ?
						IW,
						'types' => 'i'
					]
				]
			]
		];
	}
}
