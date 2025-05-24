<?php

# unsorted:
# idea: chronological view for threads <-- what? like all of them?
# think: how to fit filename and date into thread view <-- which view was this for?

# general:
# wish: something to browse similarly named flashes (just alphabetically)
#       ?do=allnames&goto=asdasd.swf -> go to the page where that flash appears
# wish: all formatted filesizes should show the byte value on hover

ini_set('max_execution_time', 3); # sec
ini_set('memory_limit',       2*1024*1024);

require_once 'dbtest/config.php';
require_once 'dbtest/db.php';
require_once 'dbtest/threadrenderer.php';

const long_tags = [
	'Unknown',
	'Anime',
	'Game',
	'Hentai',
	'Japanese',
	'Loop',
	'Other',
	'Porn',
];

const short_tags = [
	'-',
	'A',
	'G',
	'H',
	'J',
	'L',
	'?',
	'P',
];

$dbtest_header_written = false;
function dbtest_write_header($title)
{
	global $dbtest_header_written;
	$dbtest_header_written = true;
	echo
		'<!doctype html>'.
		'<html lang="en">'.
		'<head>'.
			'<meta charset="utf-8">'.
			'<title>',htmlspecialchars($title),'</title>'.
			'<link rel="stylesheet" href="',DBTEST_DIR_PUBLIC,'/layout.css">'.
			'<link rel="stylesheet" href="',DBTEST_DIR_PUBLIC,'/dark.css">'.
			'<meta name="viewport" content="width=device-width">'.
		'</head>'.
		'<body>'.
			'<a id="headerlink" href="',DBTEST_SCRIPT_PUBLIC,'">dbtest.php</a>'.
			'<hr>';
}
function dbtest_did_write_header()
{
	global $dbtest_header_written;
	return $dbtest_header_written;
}

function decode_md5($md5)
{
	switch (@strlen($md5))
	{
		case 22:
			$base64 = $md5;
			$base64 = strtr($base64, '-_', '+/');
			return base64_decode("$base64==", true);
		case 24:
			$base64 = $md5;
			$base64 = strtr($base64, '-_', '+/');
			return base64_decode($base64, true);
		case 32:
			return @hex2bin($md5);
		default:
			return null;
	}
}

function md5_to_base64url($md5)
{
	$base64 = base64_encode($md5);
	$base64 = substr($base64, 0, 22);
	$base64 = strtr($base64, '+/', '-_');
	return $base64;
}

switch ($_GET['do'] ?? '')
{
	case 'about':
	{
		require_once 'dbtest/pages/about.php';
		render_about();
		break;
	}

	case 'analyze':
	{
		if (HAVE_ANALYZE)
		{
			require_once 'dbtest/pages/analyze.php';
			render_analyze();
		}
		else
		{
			http_response_code(501);
			dbtest_write_header('not implemented - dbtest');
			echo '<p><b>Error:</b> feature not available on this server';
		}
		break;
	}

	case 'blacklist':
	{
		if (HAVE_BLACKLIST)
		{
			require_once 'dbtest/pages/blacklist.php';
			render_blacklist();
		}
		else
		{
			http_response_code(501);
			dbtest_write_header('not implemented - dbtest');
			echo '<p><b>Error:</b> feature not available on this server';
		}
		break;
	}

	case 'firstsights':
	{
		require_once 'dbtest/pages/firstsights.php';
		render_firstsights();
		break;
	}

	case 'goto':
	{
		require_once 'dbtest/pages/goto.php';
		render_goto();
		break;
	}

	case 'gotopost':
	{
		require_once 'dbtest/pages/gotopost.php';
		render_gotopost();
		break;
	}

	case 'md5info':
	{
		require_once 'dbtest/pages/md5info.php';
		render_md5info();
		break;
	}

	case 'nameinfo':
	{
		require_once 'dbtest/pages/nameinfo.php';
		render_nameinfo();
		break;
	}

	# front page
	case '':
	{
		require_once 'dbtest/pages/front.php';
		render_front();
		break;
	}

	default:
	{
		http_response_code(404);
		dbtest_write_header('404 - dbtest');
		echo
			'<p>'.
			'not found! <code>do=',
			htmlspecialchars(@strval($_GET['do'])),
			'</code> isn\'t something I understand';
	}
}

db_close();
if (dbtest_did_write_header())
{
	echo '<hr>';
	db_output_stats_table_html();
	echo
		'Mem: ',number_format(memory_get_peak_usage()/1024.0, 1),'K'.
		'<br>'.
		'Total: ',number_format((microtime(true)-$_SERVER["REQUEST_TIME_FLOAT"])*1000.0, 1),' msec';
	echo '</body></html>';
}
