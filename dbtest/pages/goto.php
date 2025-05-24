<?php

# http://127.1.1.1/dbtest.php?do=goto&to=05A380DEF85A628A601DFDE41182B4A1
# http://127.1.1.1/dbtest.php?do=goto&to=BaOA3vhaYopgHf3kEYK0oQ
function try_md5($to)
{
	if ($md5 = decode_md5($to))
	{
		$md5_hex = strtoupper(bin2hex($md5));
		return "?do=md5info&md5=$md5_hex";
	}

	# ruffle url:
	# https://archive.4plebs.org/f/ruffle/KKUl15enTzthcxV7B3x3tg/#rufflecontainer
	if (preg_match('/\/ruffle\/([0-9A-Za-z]{22})($|[\/?#])/', $to, $m))
	{
		return try_md5($m[1]);
	}

	return null;
}

# http://127.1.1.1/dbtest.php?do=goto&to=1433470080347.swf
function try_datafilename($to)
{
	if (preg_match('/^[0-9]+s?\.(?:jpg|swf)$/', $to))
	{
		db_init();
		$row = db_fetch_first_row("
		SELECT HEX(md5) AS md5
		FROM f_reposts_meta
		WHERE data_filename=?
		", [$to]);
		if ($row)
		{
			return "?do=md5info&md5={$row['md5']}";
		}
	}
	return null;
}

# http://127.1.1.1/dbtest.php?do=goto&to=3094230   - op
# http://127.1.1.1/dbtest.php?do=goto&to=3094361   - reply
# http://127.1.1.1/dbtest.php?do=goto&to=2839789   - collapsed op
# http://127.1.1.1/dbtest.php?do=goto&to=2317832,2 - ghost post
function try_postnum($to)
{
	if (preg_match('/^(?:>>(?:>\/f\/)?)?([0-9]+)(?:,([0-9]+))?$/', $to, $m))
	{
		db_init();
		$postnum = intval($m[1]);
		$subnum = !empty($m[2]) ? intval($m[2]) : 0;
		$subnum_hash = !empty($m[2]) ? ",{$m[2]}" : '';
		$row = db_fetch_first_row("
		SELECT threadnum
		FROM f_comments
		WHERE postnum=? AND subnum=?
		", [$postnum, $subnum]);
		if ($row)
		{
			$flashrow = db_fetch_first_row("
			SELECT HEX(md5) AS md5
			FROM f_reposts
			WHERE postnum=?
			", [$row['threadnum']]);
			if ($flashrow)
			{
				if ($row['threadnum'] === $postnum)
				{
					# op
					return "?do=md5info&md5={$flashrow['md5']}#t{$postnum}{$subnum_hash}";
				}
				else
				{
					# reply
					return "?do=md5info&md5={$flashrow['md5']}#com{$postnum}{$subnum_hash}";
				}
			}
		}
	}
	return null;
}

function render_goto()
{
	$to = @strval($_GET['to']);

	$url = null;
	if (!$url) $url = try_md5($to);
	if (!$url) $url = try_datafilename($to);
	if (!$url) $url = try_postnum($to);

	if ($url)
	{
		dbtest_write_header('go to - dbtest');
		$url_html = htmlspecialchars($url);
		echo
			'<p>found: <a href="',$url_html,'"><code>',$url_html,'</code></a>'.
			'<meta http-equiv="Refresh" content="0; ',$url_html,'">';
	}
	else
	{
		http_response_code(404);
		dbtest_write_header('404 - dbtest');
		echo '<p>nothing found';
	}
}
