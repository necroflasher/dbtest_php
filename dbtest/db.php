<?php

# want: option to collect EXPLAIN QUERY PLAN of every query

require_once 'dbtest/config.php';

$db = null;

# stats
$db_stats = [];
$db_nesting_level = 0; # how many levels of db_foreach_row() we're in

function db_init($readwrite=false)
{
	global $db;

	if ($db !== null)
	{
		#throw new Exception('db_init called twice');
		return;
	}

	try
	{
		$db = new SQLite3(db_file_path, ($readwrite) ? SQLITE3_OPEN_READWRITE : SQLITE3_OPEN_READONLY);
		$db->enableExceptions(true);
		$db->exec('PRAGMA busy_timeout=2000');
		$db->exec('PRAGMA journal_mode=WAL');
		# "WAL mode is safe from corruption with synchronous=NORMAL"
		$db->exec('PRAGMA synchronous=NORMAL');

		# disk is slow, please use ram
		$db->exec('PRAGMA hard_heap_limit=2000000');
		$db->exec('PRAGMA soft_heap_limit=9223372036854775807');
		$db->exec('PRAGMA temp_store=MEMORY');
		# did this do anything
		$db->exec('PRAGMA mmap_size=9223372036854775807');
	}
	catch (Exception $e)
	{
		http_response_code(503);
		# use html in case we've already written something (not text/plain)
		echo
			'<HTML lang="en">'.
			'<HEAD>'.
				'<TITLE>503 - dbtest</TITLE>'.
			'</HEAD>'.
			'<BODY>'.
				'<PRE>',htmlspecialchars(@strval($e)),'</PRE>'.
			'</BODY>'.
			'</HTML>';
		exit();
	}
}

class DbReusableQuery
{
	function __construct($sql)
	{
		global $db;
		$this->statement = $db->prepare($sql);
		if (!$this->statement)
		{
			throw new Exception('failed to prepare query');
		}
	}

	function fetch_first_row(...$params)
	{
		global $db;
		global $db_stats, $db_nesting_level;

		if ($params)
		{
			_db_bind_array_params_to_statement($this->statement, $params);
		}

		if (db_enable_query_stats)
		{
			$start = microtime(true);
		}

		$result = $this->statement->execute();

		$row = false;
		if ($result && $result->numColumns())
		{
			$row = $result->fetchArray(SQLITE3_ASSOC);
		}

		if (db_enable_query_stats)
		{
			$end = microtime(true);
			$db_stats[] = [
				'type' => str_repeat('+', $db_nesting_level).'fetch_first_row',
				'time' => $end-$start,
				'count' => ($row !== false) ? 1 : 0,
			];
		}

		if ($result)
		{
			$result->finalize();
		}

		return $row;
	}

	function fetch_all_rows(...$params)
	{
		global $db;
		global $db_stats, $db_nesting_level;

		if ($params)
		{
			_db_bind_array_params_to_statement($this->statement, $params);
		}

		if (db_enable_query_stats)
		{
			$start = microtime(true);
		}

		$rows = [];

		$result = $this->statement->execute();

		if ($result && $result->numColumns())
		{
			while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false)
			{
				$rows[] = $row;
			}
		}

		if (db_enable_query_stats)
		{
			$end = microtime(true);
			$db_stats[] = [
				'type' => str_repeat('+', $db_nesting_level).'fetch_all_rows',
				'time' => $end-$start,
				'count' => count($rows),
			];
		}

		if ($result)
		{
			$result->finalize();
		}

		return $rows;
	}

	function foreach_row($cb, ...$params)
	{
		global $db;
		global $db_stats, $db_nesting_level;

		if ($params)
		{
			_db_bind_array_params_to_statement($this->statement, $params);
		}

		if (db_enable_query_stats)
		{
			$statidx = count($db_stats);
			$db_stats[] = null;
			$db_nesting_level++;
			$tm = microtime(true);
		}

		$result = $this->statement->execute();

		$i = 0;
		$total = 0.0;
		if ($result && $result->numColumns())
		{
			for (;;)
			{
				$row = $result->fetchArray(SQLITE3_ASSOC);

				if (db_enable_query_stats)
				{
					$total += microtime(true)-$tm;
				}

				if (!$row || $cb($row, $i++) === false)
				{
					break;
				}

				# get this here so we don't count time inside the callback
				if (db_enable_query_stats)
				{
					$tm = microtime(true);
				}
			}
		}
		else
		{
			# execute() without a result, count it
			if (db_enable_query_stats)
			{
				$total += microtime(true)-$tm;
			}
		}

		if (db_enable_query_stats)
		{
			$db_nesting_level--;
			$db_stats[$statidx] = [
				'type' => str_repeat('+', $db_nesting_level).'foreach_row',
				'time' => $total,
				'count' => $i,
			];
		}

		if ($result)
		{
			$result->finalize();
		}

		return $i;
	}
}

function db_fetch_first_row($query, $params=[])
{
	return (new DbReusableQuery($query))->fetch_first_row(...$params);
}

function db_fetch_all_rows($query, $params=[])
{
	return (new DbReusableQuery($query))->fetch_all_rows(...$params);
}

function db_foreach_row($query, $cb, $params=[])
{
	return (new DbReusableQuery($query))->foreach_row($cb, ...$params);
}

# md5: column containing md5
# thumb_filename: column containing thumb_filename
# returns: sql expression to return a thumbnail url with the blacklist applied
function sql_transform_md5_thumb_url($md5, $thumb_filename)
{
	$thumbs = F_THUMBS_PUBLIC;
	$no_thumb = MISSING_THUMB_PUBLIC;
	$hi_thumb = HIDDEN_THUMB_PUBLIC;

	$sql = '';
	$sql .= 'CASE';
	$sql .= " WHEN $thumb_filename IS NULL THEN";
	$sql .= " '$no_thumb'";
	if (HAVE_BLACKLIST)
	{
		$sql .= " WHEN EXISTS(SELECT 1 FROM f_blacklist AS fb WHERE fb.md5=$md5 AND fb.level>=2) THEN";
		$sql .= " '$hi_thumb'";
	}
	if (HAVE_THUMBS)
	{
		$sql .= " ELSE";
		$sql .= " '$thumbs/'||SUBSTR($thumb_filename, 1, 4)||'/'||SUBSTR($thumb_filename, 5, 2)||'/'||$thumb_filename";
	}
	else
	{
		$sql .= " ELSE";
		$sql .= " '$no_thumb'";
	}
	$sql .= ' END';

	return $sql;
}

function _db_bind_array_params_to_statement($statement, $params)
{
	foreach ($params as $i => $val)
	{
		$typ = null;
		switch (gettype($val))
		{
			case 'integer':
				$typ = SQLITE3_INTEGER;
				break;
			case 'double':
				$typ = SQLITE3_FLOAT;
				break;
			case 'string':
				$typ = SQLITE3_TEXT;
				break;
			# sqlite3_blob would go here, but they're strings in php
			# use the thing to specify the type manually
			case 'NULL':
				$typ = SQLITE3_NULL;
				break;
			case 'array':
				$typ = $val[0];
				$val = $val[1];
				switch ($typ)
				{
					case SQLITE3_INTEGER:
					case SQLITE3_FLOAT:
					case SQLITE3_TEXT:
					case SQLITE3_BLOB:
					case SQLITE3_NULL:
						break;
					default:
						throw new Exception('unknown type given for sqlite parameter');
				}
				break;
			default:
				throw new Exception('unable to bind parameter of type '.gettype($val));
		}
		$statement->bindValue($i+1, $val, $typ);
	}
}

function db_close()
{
	global $db;

	if ($db === null)
		return false;

	$db->close();
	$db = null;

	return true;
}

function db_output_stats_table_html()
{
	global $db_stats;

	if (empty($db_stats))
		return false;

	echo
		'<table border="1" id="dbstats">'.
		'<tr>'.
			'<th>Call'.
			'<th>Msec'.
			'<th>Rows';

	$total_time = 0.0;
	$total_count = 0;

	foreach ($db_stats as $i => $row)
	{
		echo
			'<tr>'.
				'<td>',$row['type'],
				'<td align="right">',sprintf('%.3f', $row['time']*1000.0),
				'<td align="right">',$row['count'];

		$total_time += $row['time'];
		$total_count += $row['count'];
	}

	echo
		'<tr>'.
			'<td><b>Total</b>'.
			'<td align="right">',sprintf('%.3f', $total_time*1000.0),
			'<td align="right">',$total_count,
		'</table>';

	return true;
}
