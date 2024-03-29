<?php
/**
 * DbSimple_Generic: universal database connected by DSN.
 * (C) Dk Lab, http://en.dklab.ru
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 *
 * Use class DbSimple_Connect if you don't know
 * database type and parameters, but have its DSN.
 *
 * Additional keys can be added by appending a URI query string to the
 * end of the DSN.
 *
 * The format of the supplied DSN is in its fullest form:
 *   phptype(dbsyntax)://username:password@protocol+hostspec/database?option=8&another=true
 *
 * Most variations are allowed:
 *   phptype://username:password@protocol+hostspec:110//usr/db_file.db?mode=0644
 *   phptype://username:password@hostspec/database_name
 *   phptype://username:password@hostspec
 *   phptype://username@hostspec
 *   phptype://hostspec/database
 *   phptype://hostspec
 *   phptype(dbsyntax)
 *   phptype
 *
 * Parsing code is partially grabbed from PEAR DB class,
 * initial author: Tomas V.V.Cox <cox@idecnet.com>.
 *
 * Contains 3 classes:
 * - DbSimple_Generic: database factory class
 * - DbSimple_Generic_Database: common database methods
 * - DbSimple_Generic_Blob: common BLOB support
 * - DbSimple_Generic_LastError: error reporting and tracking
 *
 * Special result-set fields:
 * - ARRAY_KEY* ("*" means "anything")
 * - PARENT_KEY
 *
 * Transforms:
 * - GET_ATTRIBUTES
 * - CALC_TOTAL
 * - GET_TOTAL
 * - UNIQ_KEY
 *
 * Query attributes:
 * - BLOB_OBJ
 * - CACHE
 *
 * @author Dmitry Koterov, http://forum.dklab.ru/users/DmitryKoterov/
 * @author Konstantin Zhinko, http://forum.dklab.ru/users/KonstantinGinkoTit/
 * @author Ivan Borzenkov, http://forum.dklab.ru/users/Ivan1986/
 *
 * @version 2.x $Id: Generic.php 226 2007-09-17 21:00:15Z dk $
 */


@define('DBSIMPLE_SKIP', log(0));
/**
 * Имена специализированных колонок в резальтате,
 * которые используются как ключи в результирующем массиве
 */
@define('DBSIMPLE_ARRAY_KEY', 'ARRAY_KEY');   // hash-based resultset support
@define('DBSIMPLE_PARENT_KEY', 'PARENT_KEY'); // forrest-based resultset support

interface DbSimple_Interface
{
	/**
	 * object blob($blob_id)
	 * Create new blob
	 */
        public function blob($blob_id = null);

	/**
	 * void transaction($mode)
	 * Create new transaction.
	 */
        public function transaction($mode=null);

	/**
	 * mixed commit()
	 * Commit the transaction.
	 */
        public function commit();

	/**
	 * mixed rollback()
	 * Rollback the transaction.
	 */
        public function rollback();

	/**
	 * mixed select(string $query [, $arg1] [,$arg2] ...)
	 * Execute query and return the result.
	 */
        public function select($query) ;

	/**
	 * mixed selectPage(int &$total, string $query [, $arg1] [,$arg2] ...)
	 * Execute query and return the result.
	 * Total number of found rows (independent to LIMIT) is returned in $total
	 * (in most cases second query is performed to calculate $total).
	 */
        public function selectPage(&$total, $query);

	/**
	 * hash selectRow(string $query [, $arg1] [,$arg2] ...)
	 * Return the first row of query result.
	 * On errors return false and set last error.
	 * If no one row found, return array()! It is useful while debugging,
	 * because PHP DOES NOT generates notice on $row['abc'] if $row === null
	 * or $row === false (but, if $row is empty array, notice is generated).
	 */
        public function selectRow();

	/**
	 * array selectCol(string $query [, $arg1] [,$arg2] ...)
	 * Return the first column of query result as array.
	 */
        public function selectCol();

	/**
	 * scalar selectCell(string $query [, $arg1] [,$arg2] ...)
	 * Return the first cell of the first column of query result.
	 * If no one row selected, return null.
	 */
        public function selectCell();

	/**
	 * mixed query(string $query [, $arg1] [,$arg2] ...)
	 * Alias for select(). May be used for INSERT or UPDATE queries.
	 */
        public function query();

	/**
	 * string escape(mixed $s, bool $isIdent=false)
	 * Enclose the string into database quotes correctly escaping
	 * special characters. If $isIdent is true, value quoted as identifier
	 * (e.g.: `value` in MySQL, "value" in Firebird, [value] in MSSQL).
	 */
        public function escape($s, $isIdent=false);

	/**
	 * DbSimple_SubQuery subquery(string $query [, $arg1] [,$arg2] ...)
	 * Выполняет разворачивание плейсхолдеров без коннекта к базе
	 * Нужно для сложных запросов, состоящих из кусков, которые полезно сохранить
	 *
	 */
        public function subquery();

	/**
	 * callback setLogger(callback $logger)
	 * Set query logger called before each query is executed.
	 * Returns previous logger.
	 */
        public function setLogger($logger);

	/**
	 * callback setCacher(callback $cacher)
	 * Set cache mechanism called during each query if specified.
	 * Returns previous handler.
	 */
        public function setCacher(Zend_Cache_Backend_Interface $cacher=null);

	/**
	 * string setIdentPrefix($prx)
	 * Set identifier prefix used for $_ placeholder.
	 */
        public function setIdentPrefix($prx);
	/**
	 * string setCachePrefix($prx)
	 * Set cache prefix used in key caclulation.
	 */
        public function setCachePrefix($prx);
}


/**
 * Base class for all databases.
 * Can create transactions and new BLOBs, parse DSNs.
 *
 * Logger is COMMON for multiple transactions.
 * Error handler is private for each transaction and database.
 */
abstract class DbSimple_Generic_Database extends DbSimple_Generic_LastError implements DbSimple_Interface
{
	/**
	 * Public methods.
	 */

	/**
	 * object blob($blob_id)
	 * Create new blob
	 */
	public function blob($blob_id = null)
	{
		$this->_resetLastError();
		return $this->_performNewBlob($blob_id);
	}

	/**
	 * void transaction($mode)
	 * Create new transaction.
	 */
	public function transaction($mode=null)
	{
		$this->_resetLastError();
		$this->_logQuery('-- START TRANSACTION '.$mode);
		return $this->_performTransaction($mode);
	}

	/**
	 * mixed commit()
	 * Commit the transaction.
	 */
	public function commit()
	{
		$this->_resetLastError();
		$this->_logQuery('-- COMMIT');
		return $this->_performCommit();
	}

	/**
	 * mixed rollback()
	 * Rollback the transaction.
	 */
	public function rollback()
	{
		$this->_resetLastError();
		$this->_logQuery('-- ROLLBACK');
		return $this->_performRollback();
	}

	/**
	 * mixed select(string $query [, $arg1] [,$arg2] ...)
	 * Execute query and return the result.
	 */
	public function select($query)
	{
		$args = func_get_args();
		$total = false;
		return $this->_query($args, $total);
	}

	/**
	 * mixed selectPage(int &$total, string $query [, $arg1] [,$arg2] ...)
	 * Execute query and return the result.
	 * Total number of found rows (independent to LIMIT) is returned in $total
	 * (in most cases second query is performed to calculate $total).
	 */
	public function selectPage(&$total, $query)
	{
		$args = func_get_args();
		array_shift($args);
		$total = true;
		return $this->_query($args, $total);
	}

	/**
	 * hash selectRow(string $query [, $arg1] [,$arg2] ...)
	 * Return the first row of query result.
	 * On errors return false and set last error.
	 * If no one row found, return array()! It is useful while debugging,
	 * because PHP DOES NOT generates notice on $row['abc'] if $row === null
	 * or $row === false (but, if $row is empty array, notice is generated).
	 */
	public function selectRow()
	{
		$args = func_get_args();
		$total = false;
		$rows = $this->_query($args, $total);
		if (!is_array($rows)) return $rows;
		if (!count($rows)) return array();
		reset($rows);
		return current($rows);
	}

	/**
	 * array selectCol(string $query [, $arg1] [,$arg2] ...)
	 * Return the first column of query result as array.
	 */
	public function selectCol()
	{
		$args = func_get_args();
		$total = false;
		$rows = $this->_query($args, $total);
		if (!is_array($rows)) return $rows;
		$this->_shrinkLastArrayDimensionCallback($rows);
		return $rows;
	}

	/**
	 * scalar selectCell(string $query [, $arg1] [,$arg2] ...)
	 * Return the first cell of the first column of query result.
	 * If no one row selected, return null.
	 */
	public function selectCell()
	{
		$args = func_get_args();
		$total = false;
		$rows = $this->_query($args, $total);
		if (!is_array($rows)) return $rows;
		if (!count($rows)) return null;
		reset($rows);
		$row = current($rows);
		if (!is_array($row)) return $row;
		reset($row);
		return current($row);
	}

	/**
	 * mixed query(string $query [, $arg1] [,$arg2] ...)
	 * Alias for select(). May be used for INSERT or UPDATE queries.
	 */
	public function query()
	{
		$args = func_get_args();
		$total = false;
		return $this->_query($args, $total);
	}

	/**
	 * string escape(mixed $s, bool $isIdent=false)
	 * Enclose the string into database quotes correctly escaping
	 * special characters. If $isIdent is true, value quoted as identifier
	 * (e.g.: `value` in MySQL, "value" in Firebird, [value] in MSSQL).
	 */
	public function escape($s, $isIdent=false)
	{
		return $this->_performEscape($s, $isIdent);
	}

	/**
	 * DbSimple_SubQuery subquery(string $query [, $arg1] [,$arg2] ...)
	 * Выполняет разворачивание плейсхолдеров без коннекта к базе
	 * Нужно для сложных запросов, состоящих из кусков, которые полезно сохранить
	 *
	 */
	public function subquery()
	{
		$args = func_get_args();
		$this->_expandPlaceholders($args,$this->_placeholderNativeArgs !== null);
		return new DbSimple_SubQuery($args);
	}

	/**
	 * callback setLogger(callback $logger)
	 * Set query logger called before each query is executed.
	 * Returns previous logger.
	 */
	public function setLogger($logger)
	{
		$prev = $this->_logger;
		$this->_logger = $logger;
		return $prev;
	}

	/**
	 * callback setCacher(callback $cacher)
	 * Set cache mechanism called during each query if specified.
	 * Returns previous handler.
	 */
	public function setCacher(Zend_Cache_Backend_Interface $cacher=null)
	{
		$prev = $this->_cacher;
		$this->_cacher = $cacher;
		return $prev;
	}

	/**
	 * string setIdentPrefix($prx)
	 * Set identifier prefix used for $_ placeholder.
	 */
	public function setIdentPrefix($prx)
	{
		$old = $this->_identPrefix;
		if ($prx !== null) $this->_identPrefix = $prx;
		return $old;
	}

	/**
	 * string setCachePrefix($prx)
	 * Set cache prefix used in key caclulation.
	 */
	public function setCachePrefix($prx)
	{
		$old = $this->_cachePrefix;
		if ($prx !== null) $this->_cachePrefix = $prx;
		return $old;
	}

	/**
	 * array getStatistics()
	 * Returns various statistical information.
	 */
	public function getStatistics()
	{
		return $this->_statistics;
	}


	/**
	 * string _performEscape(mixed $s, bool $isIdent=false)
	 */
	abstract protected function _performEscape($s, $isIdent=false);

	/**
	 * object _performNewBlob($id)
	 *
	 * Returns new blob object.
	 */
	abstract protected function _performNewBlob($id=null);

	/**
	 * list _performGetBlobFieldNames($resultResource)
	 * Get list of all BLOB field names in result-set.
	 */
	abstract protected function _performGetBlobFieldNames($result);

	/**
	 * mixed _performTransformQuery(array &$query, string $how)
	 *
	 * Transform query different way specified by $how.
	 * May return some information about performed transform.
	 */
	abstract protected function _performTransformQuery(&$queryMain, $how);


	/**
	 * resource _performQuery($arrayQuery)
	 * Must return:
	 * - For SELECT queries: ID of result-set (PHP resource).
	 * - For other  queries: query status (scalar).
	 * - For error  queries: false (and call _setLastError()).
	 */
	abstract protected function _performQuery($arrayQuery);

	/**
	 * mixed _performFetch($resultResource)
	 * Fetch ONE NEXT row from result-set.
	 * Must return:
	 * - For SELECT queries: all the rows of the query (2d arrray).
	 * - For INSERT queries: ID of inserted row.
	 * - For UPDATE queries: number of updated rows.
	 * - For other  queries: query status (scalar).
	 * - For error  queries: false (and call _setLastError()).
	 */
	abstract protected function _performFetch($result);

	/**
	 * mixed _performTransaction($mode)
	 * Start new transaction.
	 */
	abstract protected function _performTransaction($mode=null);

	/**
	 * mixed _performCommit()
	 * Commit the transaction.
	 */
	abstract protected function _performCommit();

	/**
	 * mixed _performRollback()
	 * Rollback the transaction.
	 */
	abstract protected function _performRollback();

	/**
	 * string _performGetPlaceholderIgnoreRe()
	 * Return regular expression which matches ignored query parts.
	 * This is needed to skip placeholder replacement inside comments, constants etc.
	 */
	protected function _performGetPlaceholderIgnoreRe()
	{
		return '';
	}

	/**
	 * Returns marker for native database placeholder. E.g. in FireBird it is '?',
	 * in PostgreSQL - '$1', '$2' etc.
	 *
	 * @param int $n Number of native placeholder from the beginning of the query (begins from 0!).
	 * @return string String representation of native placeholder marker (by default - '?').
	 */
	protected function _performGetNativePlaceholderMarker($n)
	{
		return '?';
	}


	/**
	 * array _query($query, &$total)
	 * See _performQuery().
	 */
	private function _query($query, &$total)
	{
		$this->_resetLastError();

		// Fetch query attributes.
		$this->attributes = $this->_transformQuery($query, 'GET_ATTRIBUTES');

		// Modify query if needed for total counting.
		if ($total)
			$this->_transformQuery($query, 'CALC_TOTAL');

		$rows = false;
		$cache_it = false;
		// Кешер у нас либо null либо соответствует Zend интерфейсу
		if (!empty($this->attributes['CACHE']) && $this->_cacher)
		{

			$hash = $this->_cachePrefix . md5(serialize($query));
			// Getting data from cache if possible
			$fetchTime = $firstFetchTime = 0;
			$qStart    = microtime(true);
			$cacheData = unserialize($this->_cacher->load($hash));
			$queryTime = microtime(true) - $qStart;

			$invalCache = isset($cacheData['invalCache']) ? $cacheData['invalCache'] : null;
			$result     = isset($cacheData['result'])     ? $cacheData['result']     : null;
			$rows       = isset($cacheData['rows'])       ? $cacheData['rows']       : null;

			$cache_params = $this->attributes['CACHE'];

			// Calculating cache time to live
			$re = '/
				(?>
					([0-9]+)           #1 - hours
				h)? [ \t]*
				(?>
					([0-9]+)           #2 - minutes
				m)? [ \t]*
				(?>
					([0-9]+)           #3 - seconds
				s?)? (,)?
			/sx';
			$m = null;
			preg_match($re, $cache_params, $m);
			$ttl = (isset($m[3])?$m[3]:0)
			     + (isset($m[2])?$m[2]:0) * 60
			     + (isset($m[1])?$m[1]:0) * 3600;
			// Cutting out time param - now there are just fields for uniqKey or nothing
			$cache_params = trim(preg_replace($re, '', $cache_params, 1));

			$uniq_key = null;

			// UNIQ_KEY calculation
			if (!empty($cache_params)) {
				$dummy = null;
				// There is no need in query, cos' needle in $this->attributes['CACHE']
				$this->_transformQuery($dummy, 'UNIQ_KEY');
				$uniq_key = call_user_func_array(array(&$this, 'select'), $dummy);
				$uniq_key = md5(serialize($uniq_key));
			}
			// Check TTL?
			$ok = empty($ttl) || $cacheData;

			// Invalidate cache?
			if ($ok && $uniq_key == $invalCache) {
				$this->_logQuery($query);
				$this->_logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows);

			}
			else $cache_it = true;
		}

		if (false === $rows || true === $cache_it)
		{
			$this->_logQuery($query);

			// Run the query (counting time).
			$qStart = microtime(true);
			$result = $this->_performQuery($query);
			$fetchTime = $firstFetchTime = 0;
			if (is_resource($result)) {
				$rows = array();
				// Fetch result row by row.
				$fStart = microtime(true);
				$row = $this->_performFetch($result);
				$firstFetchTime = microtime(true) - $fStart;
				if ($row !== false) {
					$rows[] = $row;
					while ($row=$this->_performFetch($result)) {
						$rows[] = $row;
					}
				}
				$fetchTime = microtime(true) - $fStart;
			} else {
				$rows = $result;
			}
			$queryTime = microtime(true) - $qStart;

			// Log query statistics.
			$this->_logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows);

			// Prepare BLOB objects if needed.
			if (is_array($rows) && !empty($this->attributes['BLOB_OBJ']))
				foreach ($this->_performGetBlobFieldNames($result) as $name)
					foreach ($rows as $r=>$v)
						$rows[$r][$name] = $this->_performNewBlob($v[$name]);

			// Transform resulting rows.
			$result = $this->_transformResult($rows);

			// Storing data in cache
			if ($cache_it && $this->_cacher)
			{
				$this->_cacher->save(
					serialize(array(
						'invalCache' => $uniq_key,
						'result'     => $result,
						'rows'       => $rows
					)),
					$hash,
					array(),
					$ttl==0?false:$ttl
				);
			}

		}
		// Count total number of rows if needed.
		if (is_array($result) && $total) {
			$this->_transformQuery($query, 'GET_TOTAL');
			$total = call_user_func_array(array(&$this, 'selectCell'), $query);
		}

		return $result;
	}


	/**
	 * mixed _transformQuery(array &$query, string $how)
	 *
	 * Transform query different way specified by $how.
	 * May return some information about performed transform.
	 */
	private function _transformQuery(&$query, $how)
	{
		// Do overriden transformation.
		$result = $this->_performTransformQuery($query, $how);
		if ($result === true) return $result;
		// Common transformations.
		switch ($how) {
			case 'GET_ATTRIBUTES':
				// Extract query attributes.
				$options = array();
				$q = $query[0];
				$m = null;
				while (preg_match('/^ \s* -- [ \t]+ (\w+): ([^\r\n]+) [\r\n]* /sx', $q, $m)) {
					$options[$m[1]] = trim($m[2]);
					$q = substr($q, strlen($m[0]));
				}
				return $options;
			case 'UNIQ_KEY':
				$q = $this->attributes['CACHE'];
				$i = 0;
				$query = "  -- UNIQ_KEY\n";
				while(preg_match('/(\w+)\.\w+/sx', $q, $m)) {
					if($i > 0)$query .= "\nUNION\n";
					$query .= 'SELECT MAX('.$m[0].') AS M, COUNT(*) AS C FROM '.$m[1];
					$q = substr($q, strlen($m[0]));
					$i++;
				}
				return true;
		}
		// No such transform.
		$this->_setLastError(-1, "No such transform type: $how", $query);
	}


	/**
	 * void _expandPlaceholders(array &$queryAndArgs, bool $useNative=false)
	 * Replace placeholders by quoted values.
	 * Modify $queryAndArgs.
	 */
	public function _expandPlaceholders(&$queryAndArgs, $useNative=false)
	{
		$cacheCode = null;
		if ($this->_logger) {
			// Serialize is much faster than placeholder expansion. So use caching.
			$cacheCode = md5(serialize($queryAndArgs) . '|' . $useNative . '|' . $this->_identPrefix);
			if (isset($this->_placeholderCache[$cacheCode])) {
				$queryAndArgs = $this->_placeholderCache[$cacheCode];
				return;
			}
		}

		if (!is_array($queryAndArgs)) {
			$queryAndArgs = array($queryAndArgs);
		}

		$this->_placeholderNativeArgs = $useNative? array() : null;
		$this->_placeholderArgs = array_reverse($queryAndArgs);

		$query = array_pop($this->_placeholderArgs); // array_pop is faster than array_shift

		// Do all the work.
		$this->_placeholderNoValueFound = false;
		$query = $this->_expandPlaceholdersFlow($query);

		if ($useNative) {
			array_unshift($this->_placeholderNativeArgs, $query);
			$queryAndArgs = $this->_placeholderNativeArgs;
		} else {
			$queryAndArgs = array($query);
		}

		if ($cacheCode) {
			$this->_placeholderCache[$cacheCode] = $queryAndArgs;
		}
	}


	/**
	 * Do real placeholder processing.
	 * Imply that all interval variables (_placeholder_*) already prepared.
	 * May be called recurrent!
	 */
	private function _expandPlaceholdersFlow($query)
	{
		$re = '{
			(?>
				# Ignored chunks.
				(?>
					# Comment.
					-- [^\r\n]*
				)
				|
				(?>
					# DB-specifics.
					' . trim($this->_performGetPlaceholderIgnoreRe()) . '
				)
			)
			|
			(?>
				# Optional blocks
				\{
					# Use "+" here, not "*"! Else nested blocks are not processed well.
					( (?> (?>(\??)[^{}]+)  |  (?R) )* )             #1
				\}
			)
			|
			(?>
				# Placeholder
				(\?) ( [_dsafn&|\#]? )                           #2 #3
			)
		}sx';
		$query = preg_replace_callback(
			$re,
			array(&$this, '_expandPlaceholdersCallback'),
			$query
		);
		return $query;
	}

	static $join = array(
		'|' => array('inner' => ' AND ', 'outer' => ') OR (',),
		'&' => array('inner' => ' OR ', 'outer' => ') AND (',),
		'a' => array('inner' => ', ', 'outer' => '), (',),
	);

	/**
	 * string _expandPlaceholdersCallback(list $m)
	 * Internal function to replace placeholders (see preg_replace_callback).
	 */
	private function _expandPlaceholdersCallback($m)
	{
		// Placeholder.
		if (!empty($m[3])) {
			$type = $m[4];

			// Idenifier prefix.
			if ($type == '_') {
				return $this->_identPrefix;
			}

			// Value-based placeholder.
			if (!$this->_placeholderArgs) return 'DBSIMPLE_ERROR_NO_VALUE';
			$value = array_pop($this->_placeholderArgs);

			// Skip this value?
			if ($value === DBSIMPLE_SKIP) {
				$this->_placeholderNoValueFound = true;
				return '';
			}

			// First process guaranteed non-native placeholders.
			switch ($type) {
				case 's':
					if (!($value instanceof DbSimple_SubQuery))
						return 'DBSIMPLE_ERROR_VALUE_NOT_SUBQUERY';
					return $value->get($this->_placeholderNativeArgs);
				case '|':
				case '&':
				case 'a':
					if (!$value) $this->_placeholderNoValueFound = true;
					if (!is_array($value)) return 'DBSIMPLE_ERROR_VALUE_NOT_ARRAY';
					$parts = array();
					$multi = array(); //массив для двойной вложенности
					$mult = $type!='a' || is_int(key($value)) && is_array(current($value));
					foreach ($value as $prefix => $field) {
						//превращаем $value в двумерный нуменованный массив
						if (!is_array($field)) {
							$field = array($prefix => $field);
							$prefix = 0;
						}
						$prefix = is_int($prefix) ? '' : $this->escape($prefix, true) . '.';
						if (substr($prefix, 0, 2) == '?_')
							$prefix = $this->_identPrefix . substr($prefix, 2);
						//для мультиинсерта очищаем ключи - их быть не может по синтаксису
						if ($mult && $type=='a')
							$field = array_values($field);
						foreach ($field as $k => $v)
						{
							if ($v instanceof DbSimple_SubQuery)
								$v = $v->get($this->_placeholderNativeArgs);
							else
								$v = $v === null? 'NULL' : $this->escape($v);
							if (!is_int($k)) {
								$k = $this->escape($k, true);
								$parts[] = "$prefix$k=$v";
							} else {
								$parts[] = $v;
							}
						}
						if ($mult)
						{
							$multi[] = join(self::$join[$type]['inner'], $parts);
							$parts = array();
						}
					}
					return $mult ? join(self::$join[$type]['outer'], $multi) : join(', ', $parts);
				case "#":
					// Identifier.
					if (!is_array($value))
					{
						if (substr($value, 0, 2) == '?_')
							$value = $this->_identPrefix . substr($value, 2);
						return $this->escape($value, true);
					}
					$parts = array();
					foreach ($value as $table => $identifiers) {
						if (!is_array($identifiers)) $identifiers = array($identifiers);
						$prefix = '';
						if (!is_int($table)) {
								if (substr($table, 0, 2) == '?_') $table = $this->_identPrefix . substr($table, 2);
								$prefix = $this->escape($table, true) . '.';
						}
						foreach ($identifiers as $identifier)
							if ($identifier instanceof DbSimple_SubQuery)
								$parts[] = $identifier->get($this->_placeholderNativeArgs);
							else
							{
								if (!is_string($identifier))
									return 'DBSIMPLE_ERROR_ARRAY_VALUE_NOT_STRING';
								$parts[] = $prefix . ($identifier!='*'?$this->escape($identifier, true):'*');
							}
					}
					return join(', ', $parts);
				case 'n':
					// NULL-based placeholder.
					return empty($value)? 'NULL' : intval($value);
			}

			// In non-native mode arguments are quoted.
			if ($value === null) return 'NULL';

			switch ($type) {
				case 'd':
					return intval($value);
				case 'f':
					return str_replace(',', '.', floatval($value));
			}

			// нативные плейсхолдеры необходимо обработать после числовых из-за бага в PDO
			// Native arguments are not processed.
			if ($this->_placeholderNativeArgs !== null) {
				$this->_placeholderNativeArgs[] = $value;
				return $this->_performGetNativePlaceholderMarker(count($this->_placeholderNativeArgs) - 1);
			}

			if (!is_scalar($value))
				return 'DBSIMPLE_ERROR_VALUE_NOT_SCALAR';

			// By default - escape as string.
			return $this->escape($value);
		}

		// Optional block.
		if (isset($m[1]) && strlen($block=$m[1]))
		{
			$prev  = $this->_placeholderNoValueFound;
			if ($this->_placeholderNativeArgs !== null)
				$prevPh = $this->_placeholderNativeArgs;

			// Проверка на {?  } - условный блок
			$skip = false;
			if ($m[2]=='?')
			{
				$skip = array_pop($this->_placeholderArgs) === DBSIMPLE_SKIP;
				$block[0] = ' ';
			}

			$block = $this->_expandOptionalBlock($block);

			if ($skip)
				$block = '';

			if ($this->_placeholderNativeArgs !== null)
				if ($this->_placeholderNoValueFound)
					$this->_placeholderNativeArgs = $prevPh;
			$this->_placeholderNoValueFound = $prev; // recurrent-safe
			return $block;
		}

		// Default: skipped part of the string.
		return $m[0];
	}

	private function _expandOptionalBlock($block)
	{
		$alts = array();
		$alt = '';
		$sub=0;
		$exp = explode('|',$block);
		// Оптимизация, так как в большинстве случаев | не используется
		if (count($exp)==1)
			$alts=$exp;
		else
			foreach ($exp as $v)
			{
				// Реализуем автоматный магазин для нахождения нужной скобки
				// На суммарную парность скобок проверять нет необходимости - об этом заботится регулярка
				$sub+=substr_count($v,'{');
				$sub-=substr_count($v,'}');
				if ($sub>0)
					$alt.=$v.'|';
				else
				{
					$alts[]=$alt.$v;
					$alt='';
				}
			}
		$r='';
		foreach ($alts as $block)
		{
			$this->_placeholderNoValueFound = false;
			$block = $this->_expandPlaceholdersFlow($block);
			// Необходимо пройти все блоки, так как если пропустить оставшиесь,
			// то это нарушит порядок подставляемых значений
			if ($this->_placeholderNoValueFound == false && $r=='')
				$r = ' '.$block.' ';
		}
		return $r;
	}


	/**
	 * void _setLastError($code, $msg, $query)
	 * Set last database error context.
	 * Aditionally expand placeholders.
	 */
	protected function _setLastError($code, $msg, $query)
	{
		if (is_array($query)) {
			$this->_expandPlaceholders($query, false);
			$query = $query[0];
		}
		return parent::_setLastError($code, $msg, $query);
	}


	/**
	 * Convert SQL field-list to COUNT(...) clause
	 * (e.g. 'DISTINCT a AS aa, b AS bb' -> 'COUNT(DISTINCT a, b)').
	 */
	private function _fieldList2Count($fields)
	{
		$m = null;
		if (preg_match('/^\s* DISTINCT \s* (.*)/sx', $fields, $m)) {
			$fields = $m[1];
			$fields = preg_replace('/\s+ AS \s+ .*? (?=,|$)/sx', '', $fields);
			return "COUNT(DISTINCT $fields)";
		} else {
			return 'COUNT(*)';
		}
	}


	/**
	 * array _transformResult(list $rows)
	 * Transform resulting rows to various formats.
	 */
	private function _transformResult($rows)
	{
		// Результат не массив - выходим
		if (!is_array($rows) || !$rows)
			return $rows;

		// Find ARRAY_KEY* AND PARENT_KEY fields in field list.
		$pk = null;
		$ak = array();
		foreach (array_keys(current($rows)) as $fieldName)
			if (0 == strncasecmp($fieldName, DBSIMPLE_ARRAY_KEY, strlen(DBSIMPLE_ARRAY_KEY)))
				$ak[] = $fieldName;
			elseif (0 == strncasecmp($fieldName, DBSIMPLE_PARENT_KEY, strlen(DBSIMPLE_PARENT_KEY)))
				$pk = $fieldName;

		if (!$ak)
			return $rows;

		natsort($ak); // sort ARRAY_KEY* using natural comparision
		// Tree-based array? Fields: ARRAY_KEY, PARENT_KEY
		if ($pk !== null)
			return $this->_transformResultToForest($rows, $ak[0], $pk);
		// Key-based array? Fields: ARRAY_KEY.
		return $this->_transformResultToHash($rows, $ak);
	}


	/**
	 * Converts rowset to key-based array.
	 *
	 * @param array $rows   Two-dimensional array of resulting rows.
	 * @param array $ak     List of ARRAY_KEY* field names.
	 * @return array        Transformed array.
	 */
	private function _transformResultToHash(array $rows, array $arrayKeys)
	{
		$result = array();
		foreach ($rows as $row) {
			// Iterate over all of ARRAY_KEY* fields and build array dimensions.
			$current =& $result;
			foreach ($arrayKeys as $ak) {
				$key = $row[$ak];
				unset($row[$ak]); // remove ARRAY_KEY* field from result row
				if ($key !== null) {
					$current =& $current[$key];
				} else {
					// IF ARRAY_KEY field === null, use array auto-indices.
					$tmp = array();
					$current[] =& $tmp;
					$current =& $tmp;
					unset($tmp); // we use $tmp, because don't know the value of auto-index
				}
			}
			$current = $row; // save the row in last dimension
		}
		return $result;
	}


	/**
	 * Converts rowset to the forest.
	 *
	 * @param array $rows       Two-dimensional array of resulting rows.
	 * @param string $idName    Name of ID field.
	 * @param string $pidName   Name of PARENT_ID field.
	 * @return array            Transformed array (tree).
	 */
	private function _transformResultToForest(array $rows, $idName, $pidName)
	{
		$children = array(); // children of each ID
		$ids = array();
		// Collect who are children of whom.
		foreach ($rows as $i=>$r) {
			$row =& $rows[$i];
			$id = $row[$idName];
			if ($id === null) {
				// Rows without an ID are totally invalid and makes the result tree to
				// be empty (because PARENT_ID = null means "a root of the tree"). So
				// skip them totally.
				continue;
			}
			$pid = $row[$pidName];
			if ($id == $pid) $pid = null;
			$children[$pid][$id] =& $row;
			if (!isset($children[$id])) $children[$id] = array();
			$row['childNodes'] =& $children[$id];
			$ids[$id] = true;
		}
		// Root elements are elements with non-found PIDs.
		$forest = array();
		foreach ($rows as $i=>$r) {
			$row =& $rows[$i];
			$id = $row[$idName];
			$pid = $row[$pidName];
			if ($pid == $id) $pid = null;
			if (!isset($ids[$pid])) {
				$forest[$row[$idName]] =& $row;
			}
			unset($row[$idName]);
			unset($row[$pidName]);
		}
		return $forest;
	}


	/**
	 * Replaces the last array in a multi-dimensional array $V by its first value.
	 * Used for selectCol(), when we need to transform (N+1)d resulting array
	 * to Nd array (column).
	 */
	private function _shrinkLastArrayDimensionCallback(&$v)
	{
		if (!$v) return;
		reset($v);
		if (!is_array($firstCell = current($v))) {
			$v = $firstCell;
		} else {
			array_walk($v, array(&$this, '_shrinkLastArrayDimensionCallback'));
		}
	}


	/**
	 * void _logQuery($query, $noTrace=false)
	 * Must be called on each query.
	 * If $noTrace is true, library caller is not solved (speed improvement).
	 */
	protected function _logQuery($query, $noTrace=false)
	{
		if (!$this->_logger) return;
		$this->_expandPlaceholders($query, false);
		$args = array();
		$args[] =& $this;
		$args[] = $query[0];
		$args[] = $noTrace? null : $this->findLibraryCaller();
		return call_user_func_array($this->_logger, $args);
	}


	/**
	 * void _logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows)
	 * Log information about performed query statistics.
	 */
	protected function _logQueryStat($queryTime, $fetchTime, $firstFetchTime, $rows)
	{
		// Always increment counters.
		$this->_statistics['time'] += $queryTime;
		$this->_statistics['count']++;

		// If no logger, economize CPU resources and actually log nothing.
		if (!$this->_logger)
			return;

		$dt = round($queryTime * 1000);
		$firstFetchTime = round($firstFetchTime*1000);
		$tailFetchTime = round($fetchTime * 1000) - $firstFetchTime;
		$log = "  -- ";
		if ($firstFetchTime + $tailFetchTime) {
			$log = sprintf("  -- %d ms = %d+%d".($tailFetchTime? "+%d" : ""), $dt, $dt-$firstFetchTime-$tailFetchTime, $firstFetchTime, $tailFetchTime);
		} else {
			$log = sprintf("  -- %d ms", $dt);
		}
		$log .= "; returned ";

		if (!is_array($rows)) {
			$log .= $this->escape($rows);
		} else {
			$detailed = null;
			if (count($rows) == 1) {
				$len = 0;
				$values = array();
				foreach ($rows[0] as $k=>$v) {
					$len += strlen($v);
					if ($len > $this->MAX_LOG_ROW_LEN) {
						break;
					}
					$values[] = $v === null? 'NULL' : $this->escape($v);
				}
				if ($len <= $this->MAX_LOG_ROW_LEN) {
					$detailed = "(" . preg_replace("/\r?\n/", "\\n", join(', ', $values)) . ")";
				}
			}
			if ($detailed) {
				$log .= $detailed;
			} else {
				$log .= count($rows). " row(s)";
			}
		}

		$this->_logQuery($log, true);
	}

	/**
	 * mixed _cache($hash, $result=null)
	 * Calls cache mechanism if possible.
	 */
	private function _cache($hash, $result=null)
	{
		if (is_callable($this->_cacher)) {
			return call_user_func($this->_cacher, $hash, $result);
		} else if (is_object($this->_cacher) && method_exists($this->_cacher, 'get') && method_exists($this->_cacher, 'save')) {
			if (null === $result)
				return $this->_cacher->get($hash);
			else
				$this->_cacher->save($result, $hash);
		}
		else return false;
	}

	// Identifiers prefix (used for ?_ placeholder).
	protected $_identPrefix = ''; /* changed to protected by alex@cgi-central.net */

	// Queries statistics.
	private $_statistics = array(
		'time'  => 0,
		'count' => 0,
	);

	private $_cachePrefix = '';

	private $_logger = null;
	private $_cacher = null;
	private $_placeholderArgs, $_placeholderNativeArgs, $_placeholderCache=array();
	private $_placeholderNoValueFound;

	/**
	 * When string representation of row (in characters) is greater than this,
	 * row data will not be logged.
	 */
	private $MAX_LOG_ROW_LEN = 128;
}


/**
 * Интерфейс BLOB.
 * Описывает функции обекта типа BLOB - ему должны соответствовать классы, возвращаемые в качестве BLOB полей.
 */
interface DbSimple_Generic_Blob
{
	/**
	 * string read(int $length)
	 * Returns following $length bytes from the blob.
	 */
	public function read($len);

	/**
	 * string write($data)
	 * Appends data to blob.
	 */
	public function write($data);

	/**
	 * int length()
	 * Returns length of the blob.
	 */
	public function length();

	/**
	 * blobid close()
	 * Closes the blob. Return its ID. No other way to obtain this ID!
	 */
	public function close();

}

/**
 * Класс для хранения подзапроса - результата выполнения функции
 * DbSimple_Generic_Database::subquery
 *
 */
class DbSimple_SubQuery
{
	private $query=array();


	public function __construct(array $q)
	{
		$this->query = $q;
	}

	/**
	 * Возвращает сам запрос и добавляет плейсхолдеры в массив переданный по ссылке
	 *
	 * @param &array|null - ссылка на массив плейсхолдеров
	 * @return string
	 */
	public function get(&$ph)
	{
		if ($ph !== null)
			$ph = array_merge($ph, array_slice($this->query,1,null,true));
		return $this->query[0];
	}

}

/**
 * Support for error tracking.
 * Can hold error messages, error queries and build proper stacktraces.
 */
abstract class DbSimple_Generic_LastError
{
	public $error = null;
	public $errmsg = null;
	private $showStack = false;
	private $errorHandler = null;
	private $ignoresInTraceRe = 'DbSimple_.*::.* | call_user_func.*';

	/**
	 * abstract void _logQuery($query)
	 * Must be overriden in derived class.
	 */
	protected abstract function _logQuery($query);

	/**
	 * void _resetLastError()
	 * Reset the last error. Must be called on correct queries.
	 */
	protected function _resetLastError()
	{
		$this->error = $this->errmsg = null;
	}

	/**
	 * void _setLastError(int $code, string $message, string $query)
	 * Fill $this->error property with error information. Error context
	 * (code initiated the query outside DbSimple) is assigned automatically.
	 */
	protected function _setLastError($code, $msg, $query)
	{
		$context = "unknown";
		$this->error = array(
			'code'    => $code,
			'message' => rtrim($msg),
			'query'   => $query,
			'context' => '',
		);
		if ($t = $this->findLibraryCaller($this->showStack))
		{
			if (!$this->showStack)
				$this->error['context'] = (isset($t['file'])? $t['file'] : '?') .
					' line ' . (isset($t['line'])? $t['line'] : '?');
			else
			{
				$this->error['context'] = (isset($t[0]['file'])? $t[0]['file'] : '?') .
					' line ' . (isset($t[0]['line'])? $t[0]['line'] : '?');
				$this->error['stack'] = array();
				foreach($t as $f)
					$this->error['stack'][] = (isset($f['class']) ? $f['class'].$f['type'] : '').
						(isset($f['function']) ? $f['function'] : '?').'() '.
						(isset($f['file'])? $f['file'] : '?') . ':' . (isset($f['line'])? $f['line'] : '?');
			}
		}
		$this->errmsg = rtrim($msg) . ($this->error['context']? ' at '.$this->error['context'] : '');

		$this->_logQuery("  -- error #".$code.": ".preg_replace('/(\r?\n)+/s', ' ', $this->errmsg));

		if (is_callable($this->errorHandler))
        {
			call_user_func($this->errorHandler, $this->errmsg, $this->error);
		}

		return false;
	}


	/**
	 * callback setErrorHandler(callback $handler, bool $stack)
	 * Set new error handler called on database errors.
	 * Handler gets 3 arguments:
	 * - error message
	 * - full error context information (last query etc.)
	 */
	public function setErrorHandler($handler, $stack=false)
	{
		$prev = $this->errorHandler;
		$this->errorHandler = $handler;
		$this->showStack = $stack;
		// In case of setting first error handler for already existed
		// error - call the handler now (usual after connect()).
		if (!$prev && $this->error)
			call_user_func($this->errorHandler, $this->errmsg, $this->error);
		return $prev;
	}

	/**
	 * void addIgnoreInTrace($reName)
	 * Add regular expression matching ClassName::functionName or functionName.
	 * Matched stack frames will be ignored in stack traces passed to query logger.
	 */
	public function addIgnoreInTrace($name)
	{
		$this->ignoresInTraceRe .= "|" . $name;
	}

	/**
	 * array of array findLibraryCaller()
	 * Return part of stacktrace before calling first library method.
	 * Used in debug purposes (query logging etc.).
	 */
	public function findLibraryCaller($all = false)
	{
		$caller = call_user_func(
			array(&$this, 'debug_backtrace_smart'),
			$this->ignoresInTraceRe,
			!$all
		);
		return $caller;
	}

	/**
	 * array debug_backtrace_smart($ignoresRe=null, $returnCaller=false)
	 *
	 * Return stacktrace. Correctly work with call_user_func*
	 * (totally skip them correcting caller references).
	 * If $returnCaller is true, return only first matched caller,
	 * not all stacktrace.
	 *
	 * @version 2.03
	 */
	private function debug_backtrace_smart($ignoresRe=null, $returnCaller=false)
	{
		$trace = debug_backtrace();

		if ($ignoresRe !== null)
			$ignoresRe = "/^(?>{$ignoresRe})$/six";
		$smart = array();
		$framesSeen = 0;
		for ($i=0, $n=count($trace); $i<$n; $i++) {
			$t = $trace[$i];
			if (!$t) continue;

			// Next frame.
			$next = isset($trace[$i+1])? $trace[$i+1] : null;

			// Dummy frame before call_user_func* frames.
			if (!isset($t['file'])) {
				$t['over_function'] = $trace[$i+1]['function'];
				$t = $t + $trace[$i+1];
				$trace[$i+1] = null; // skip call_user_func on next iteration
				$next = isset($trace[$i+2])? $trace[$i+2] : null; // Correct Next frame.
			}

			// Skip myself frame.
			if (++$framesSeen < 2) continue;

			// 'class' and 'function' field of next frame define where
			// this frame function situated. Skip frames for functions
			// situated in ignored places.
			if ($ignoresRe && $next) {
				// Name of function "inside which" frame was generated.
				$frameCaller = (isset($next['class'])? $next['class'].'::' : '') . (isset($next['function'])? $next['function'] : '');
				if (preg_match($ignoresRe, $frameCaller)) continue;
			}

			// On each iteration we consider ability to add PREVIOUS frame
			// to $smart stack.
			if ($returnCaller) return $t;
			$smart[] = $t;
		}
		return $smart;
	}

}

abstract class DbSimple_MySql extends DbSimple_Generic_Database {}

class DbSimple_Mypdo extends DbSimple_MySql
{
    public $PDO;
    public $attributes;
    public $_lastQuery;

	public function __construct($dsn)
	{
		$base = preg_replace('{^/}s', '', $dsn['path']);
		if (!class_exists('PDO'))
			return $this->_setLastError("-1", "PDO extension is not loaded", "PDO");

		try {

            if (strpos($dsn['host'], ':') !== false) {
                list($host, $socket) = @explode(':', $dsn['host']);
            } else {
                $host= $dsn['host'];
                $socket = '';
            }

            $options = ($dsn['pdo_options'] ?? []) + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_PERSISTENT => $dsn['persist'] ?? false,
                PDO::ATTR_TIMEOUT => $dsn['timeout'] ?? 0,
                //to fix the next error on some setups "This command is not supported in the prepared statement protocol yet"
                //uncomment next line
                //PDO::ATTR_EMULATE_PREPARES => true,
                //did not work reliable PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.(isset($dsn['enc'])?$dsn['enc']:'UTF8'),
            ];

            $this->PDO = new PDO(
			    'mysql:host='.$host.(empty($dsn['port'])?'':';port='.$dsn['port']).(empty($socket)?'':';unix_socket='.$socket).';dbname='.$base.';charset=utf8mb4',
                $dsn['user'],
                isset($dsn['pass'])?$dsn['pass']:'',
                $options
            );
		} catch (PDOException $e) {
            $code = $e->getCode();
            if (empty($code) && preg_match('/^SQLSTATE\[\d+\]\s+\[(\d+)\]/', $e->getMessage(), $regs))
            {
                $code = $regs[1];
            }
			$this->_setLastError($code , $e->getMessage(), 'new PDO');
		}
	}

    /** @access private */
    public function _isConnected()
    {
        return (bool)$this->PDO;
    }

	protected function _performGetPlaceholderIgnoreRe()
	{
		return '
			"   (?> [^"\\\\]+|\\\\"|\\\\)*    "   |
			\'  (?> [^\'\\\\]+|\\\\\'|\\\\)* \'   |
			`   (?> [^`]+ | ``)*              `   |   # backticks
			/\* .*?                          \*/      # comments
		';
	}

	protected function _performEscape($s, $isIdent=false)
	{
		if (!$isIdent) {
			return $this->PDO->quote($s ?? '');
		} else {
			return "`" . str_replace('`', '``', $s) . "`";
		}
	}

	protected function _performTransaction($parameters=null)
	{
		return $this->PDO->beginTransaction();
	}

	protected function _performCommit()
	{
		return $this->PDO->commit();
	}

	protected function _performRollback()
	{
		return $this->PDO->rollBack();
	}

	protected function _performQuery($queryMain, $resultOnly = false)
	{
		$this->_lastQuery = $queryMain;
		$this->_expandPlaceholders($queryMain, false);
        if (!$this->PDO)
            throw new Exception("Trying to run query, but not connected to database");
		$p = $this->PDO->query($queryMain[0]);
		if (!$p)
			return $this->_setDbError($p,$queryMain[0]);
		if ($p->errorCode()!=0)
			return $this->_setDbError($p,$queryMain[0]);
        if ($resultOnly) return $p;
		if (preg_match('/^\s* INSERT \s+/six', $queryMain[0]))
			return $this->PDO->lastInsertId();
		if ($p->columnCount()==0)
			return $p->rowCount();
		//Если у нас в запросе есть хотя-бы одна колонка - это по любому будет select
		$p->setFetchMode(PDO::FETCH_ASSOC);
		$res = $p->fetchAll();
		$p->closeCursor();
		return $res;
	}

	protected function _performTransformQuery(&$queryMain, $how)
	{
		// If we also need to calculate total number of found rows...
		switch ($how)
		{
			// Prepare total calculation (if possible)
			case 'CALC_TOTAL':
				$m = null;
				if (preg_match('/^(\s* SELECT)(.*)/six', $queryMain[0], $m))
					$queryMain[0] = $m[1] . ' SQL_CALC_FOUND_ROWS' . $m[2];
				return true;

			// Perform total calculation.
			case 'GET_TOTAL':
				// Built-in calculation available?
				$queryMain = array('SELECT FOUND_ROWS()');
				return true;
		}

		return false;
	}

	protected function _setDbError($obj,$q)
	{
		$info=$obj?$obj->errorInfo():$this->PDO->errorInfo();
		return $this->_setLastError($info[1], $info[2], $q);
	}

	protected function _performNewBlob($id=null)
	{
	}

	protected function _performGetBlobFieldNames($result)
	{
		return array();
	}

	protected function _performFetch($result)
	{
		return $result;
	}
    /* added by alex@cgi-central.net */
    /** @return PDOStatement */
    public function queryResultOnly($sql)
    {
        $query = func_get_args();
        $this->_logQuery($query);
        // Run the query (counting time).
        $qStart = microtime(true);
        $result = $this->_performQuery($query, true);
        $queryTime = microtime(true) - $qStart;
        // Log query statistics.
        $this->_logQueryStat($queryTime, null, null, null);
        return $result;
    }
    /* added by alex@cgi-central.net */
    /**
     * Runs a query without any additional overal with no substitution
     * @return PDOStatement */
    public function queryQuick($sql)
    {
		$p = $this->PDO->query($sql);
		if (!$p)
			return $this->_setDbError($p, $sql);
		if ($p->errorCode()!=0)
			return $this->_setDbError($p, $sql);
        return $p;
    }
    public function fetchRow($st)
    {
        return $st->fetch(PDO::FETCH_ASSOC);
    }
    public function fetchRows($st)
    {
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchArray($st)
    {
        return $st->fetch(PDO::FETCH_ASSOC);
    }
    public function freeResult($st)
    {
        $st->closeCursor();
    }
    public function getPrefix()
    {
        return @$this->_identPrefix;
    }
    public function expandPlaceholders($queryAndArgs)
    {
        if (is_array($queryAndArgs))
        {
            if (count($queryAndArgs)>1)
                $this->_expandPlaceholders($queryAndArgs);
            return $queryAndArgs[0];
        } else {
            return (string)$queryAndArgs;
        }
    }

    function escapeWithPlaceholders($s, $_){
        $queryAndArgs = func_get_args();
        $this->_expandPlaceholders($queryAndArgs);
        return $queryAndArgs[0];
    }

    public function quote($s)
    {
        return trim($this->PDO->quote($s), "'");
    }
    public function __sleep()
    {
        return array();
    }
}

class DbSimple_Mypdo_Blob implements DbSimple_Generic_Blob
{
	// MySQL does not support separate BLOB fetching.
	private $blobdata = null;
	private $curSeek = 0;

	public function __construct(&$database, $blobdata=null)
	{
		$this->blobdata = $blobdata;
		$this->curSeek = 0;
	}

	public function read($len)
	{
		$p = $this->curSeek;
		$this->curSeek = min($this->curSeek + $len, strlen($this->blobdata));
		return substr($this->blobdata, $this->curSeek, $len);
	}

	public function write($data)
	{
		$this->blobdata .= $data;
	}

	public function close()
	{
		return $this->blobdata;
	}

	public function length()
	{
		return strlen($this->blobdata);
	}
}