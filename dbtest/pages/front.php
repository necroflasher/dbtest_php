<?php

# wish: thumbs should show the number of comments
# idea: thumbs should show filename if it's pure white or something
# idea: make the layout look more "physical" - increase padding and outlines, clearly divide things

# WISH: configurable rows/cols
# WISH: make the refresh button bigger

# WISH: make browsable collections
# - user curated
# - based on some attribute from analyzing swf

# https://github.com/janearc/kx/blob/master/inc/func/numberformatting.php#L2
function fmt_size($bytes)
{
	if ($bytes < 1000)
		return sprintf("%dB", $bytes);
	else if ($bytes < 1000000)
		return sprintf("%.02fKB", $bytes/1024);
	else if ($bytes < 1000000000)
		return sprintf("%.02fMB", $bytes/(1024*1024));
	else
		return sprintf("%.02fGB", $bytes/(1024*1024*1024));
}

function get_stats()
{
	$row = [];
	$row['threads_count'] = db_fetch_first_row("SELECT MAX(rowid) AS v FROM f_reposts")['v'];
	$row['flashes_count'] = db_fetch_first_row("SELECT MAX(rowid) AS v FROM f_reposts_meta")['v'];
	$row['oldest_ts'] = db_fetch_first_row("SELECT SUBSTR(timestamp, 1, 10) AS v FROM f_reposts WHERE postnum=(SELECT MIN(postnum) FROM f_reposts)")['v'];
	$row['newest_ts'] = db_fetch_first_row("SELECT SUBSTR(timestamp, 1, 10) AS v FROM f_reposts WHERE postnum=(SELECT MAX(postnum) FROM f_reposts)")['v'];
	return $row;
}

function get_frontpage_flashes($limit, $totalcount)
{
	$fetchcount = $limit;
	# fetch a couple rows extra since they might be dropped by filtering
	# this is a fast query so this doesn't make a difference in page load time
	if (HAVE_BLACKLIST)
	{
		$fetchcount += 5;
	}

	for (;;)
	{
		# this works by picking $count random rowids from reposts_meta
		# not affected by how many times the flash is posted
		$rowids = [];
		$rowidset = [];
		for ($i = 0; $i < $fetchcount; /* empty */)
		{
			$rowid = mt_rand(1, $totalcount);
			if (!array_key_exists($rowid, $rowidset))
			{
				$rowids[$i++] = $rowid;
				$rowidset[$rowid] = true;
			}
		}
		$rowids_sql = '('.implode(',', $rowids).')';

		$is_blacklisted_sql = HAVE_BLACKLIST
			? '(EXISTS(SELECT 1 FROM f_blacklist WHERE md5=fm.md5))'
			: '(0)';
		$rows = db_fetch_all_rows("
		SELECT
			fr.filename,
			fm.filesize,
			fm.reposts,
			HEX(fm.md5) AS md5,
			fm.width_px||'x'||fm.height_px AS dim,
			".sql_transform_md5_thumb_url('fm.md5', 'fm.thumb_filename')." AS thumb_url
		FROM f_reposts AS fr
			JOIN (
				SELECT *
				FROM f_reposts_meta AS fm
				WHERE fm.rowid IN $rowids_sql AND
				NOT $is_blacklisted_sql) fm
			ON fm.md5=fr.md5
		WHERE fm.first_seen=fr.timestamp
		ORDER BY fm.reposts DESC, fr.md5 ASC
		");
		$rowcount = count($rows);

		# if we got too few rows, just reroll the entire thing
		# they're sorted inside the query so reusing the old result wouldn't be trivial
		# this should rarely happen now that we fetch extra rows
		if ($rowcount < $limit)
			continue;

		# drop extra items
		# note: randomize to keep it fair
		# removing from tail would bias it against unpopular flashes
		$oldcount = $rowcount;
		while ($rowcount > $limit)
		{
			$i = mt_rand(0, $oldcount-1);
			if (isset($rows[$i]))
			{
				unset($rows[$i]);
				$rowcount--;
			}
		}

		return $rows;
	}
}

function render_front()
{
	db_init();
	dbtest_write_header('dbtest');

	$ign = intval(@$_GET['click']);

	echo '<script>var DBTEST_DIR_PUBLIC=',json_encode(DBTEST_DIR_PUBLIC),'</script>';
	echo '<script async src="',DBTEST_DIR_PUBLIC,'/frontpage.js"></script>';

	$sidepic_cols = 5;
	$sidepic_rows = 6;
	$sidepic_width = 92;
	$limit = $sidepic_rows*$sidepic_cols;

	$stats = get_stats();
	$flashes = get_frontpage_flashes($limit, $stats['flashes_count']);

	#
	# side: main pic, thumbs
	# note: this must come before stats so that it wraps correctly
	#
	echo '<div style="width: ',ceil($sidepic_width*$sidepic_cols),'px; float: right;">';
	foreach ($flashes as $i => $row)
	{
		$filename_html = htmlspecialchars($row['filename']);
		echo
			'<a'.
			' id="thumb',$i,'"'.
			' href="?do=md5info&md5=',$row['md5'],'"'.
			' onmouseover="set_highlight_link(',$i,')"'.
			'>'.
				'<img'.
					' title="',$filename_html,'"'.
					' src="',$row['thumb_url'],'"'.
					' width="',$sidepic_width,'"'.
					' height="',$sidepic_width,'"'.
				 '>'.
			'</a>';
	}
	echo '</div>';

	#
	# top: stats blurb
	#
	echo
		'<p>'.
		'Database contains <tt>',$stats['threads_count'],'</tt> threads,'.
		' ',
		'<tt>',$stats['flashes_count'],'</tt> unique flashes posted between'.
		' '.
		'<tt>',$stats['oldest_ts'],'</tt>'.
		' and '.
		'<tt>',$stats['newest_ts'],'</tt>'.
		'.'.
		' '.
		'<a href="?do=about" style="font-size: smaller;">More info</a>';

	#
	# left: flash list
	#
	echo
		'<p>'.
		'',$limit,' random flashes:'.
		'<ul>';
	foreach ($flashes as $i => $row)
	{
		$filename_html = htmlspecialchars($row['filename']);
		$shortname_html = htmlspecialchars(mb_strimwidth($row['filename'], 0, 30, '(...)', 'utf-8'));
		$size_fmt = fmt_size($row['filesize']);

		echo
			'<li>'.
			'<span title="times posted">[',$row['reposts'],']</span>'.
			' '.
			'<a'.
				' title="',$filename_html,'"'.
				' id="link',$i,'"'.
				' href="?do=md5info&md5=',$row['md5'],'"'.
				' onmouseover="set_highlight_thumb(',$i,')"'.
				'>',
					$shortname_html,
			'</a>'.
			' '.
			'(',$row['dim'],', ',$size_fmt,')';
	}
	echo '</ul>';

	#
	# bottom text
	#
	echo
		'<a href="dbtest.php?click=',($ign+1),'" id="refresh_link">refresh</a>'.
		'<p>'.
		'see also:',
		' '.
		'<a href="?do=firstsights"><tt>do=firstsights</tt></a>';
	if (HAVE_BLACKLIST)
	echo
		', '.
		'<a href="?do=blacklist"><tt>do=blacklist</tt></a>';
	echo
		'<form method="GET" action="">'.
			'<input type="hidden" name="do" value="goto">'.
			'<input type="search" required name="to" ondrop="goto_drop_handler();" ondragenter="goto_dragenter_handler();" ondragleave="goto_dragleave_handler();">'.
			'<input type="submit" value="Go to">'.
		'</form>'.
		'';
}
