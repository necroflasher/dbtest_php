<?php

# WANT: show dim, size

function render_nameinfo()
{
	# http://r1.local/dbtest.php?do=nameinfo&filename=dancingstreetguys.swf
	# http://r1.local/dbtest.php?do=nameinfo&filename=hat.swf
	# http://r1.local/dbtest.php?do=nameinfo&filename=lolicatgirls.swf
	# http://r1.local/dbtest.php?do=nameinfo&filename=whatcouldthisbeIwonder.swf
	db_init();
	dbtest_write_header('nameinfo - dbtest');

	$filename = @strval($_GET['filename']);

	if (!str_ends_with($filename, '.swf'))
		$filename .= '.swf';

	# eh, i think there might be some smart way to do this in the same query
	$q_popname = new DbReusableQuery("
	SELECT COUNT() AS cnt, filename
	FROM f_reposts
	WHERE md5=?
	GROUP BY filename
	ORDER BY cnt DESC, filename ASC
	");

	$idx = 0;
	$cb = function ($row) use (&$idx, $filename, &$q_popname)
	{
		if (!$idx++)
		{
			echo
				'<p>Files posted as <b>',htmlspecialchars($filename),'</b>:'.
				'<table border>'.
				'<thead>'.
					'<th>Thumb'.
					'<th>Cnt'.
					'<th>MD5'.
					'<th>Popular name'.
				'</thead>'.
				'<tbody>';
		}
		$p = $q_popname->fetch_first_row([SQLITE3_BLOB, $row['rawmd5']]);
		echo
			'<tr>'.
				'<td>'.
					'<a href="?do=md5info&md5=',$row['hexmd5'],'">'.
						'<img src="',$row['thumb_url'],'" width=92 height=92>'.
					'</a>'.
				'<td align="right">',
					$row['cnt'],
				'<td>',
					'<a href="?do=md5info&md5=',$row['hexmd5'],'">'.
						'<tt>',$row['hexmd5'],'</tt>'.
					'</a>'.
				'<td>',
					htmlspecialchars($p['filename']),
			'</tr>';
	};

	db_foreach_row("
	SELECT
		COUNT()     AS cnt,
		HEX(fr.md5) AS hexmd5,
		fr.md5      AS rawmd5,
		".sql_transform_md5_thumb_url('fm.md5', 'fm.thumb_filename')." AS thumb_url
	FROM f_reposts AS fr
	JOIN f_reposts_meta AS fm ON fm.md5=fr.md5
	WHERE filename=?
	GROUP BY fr.md5
	ORDER BY
		cnt    DESC,
		fr.md5 ASC
	", $cb, [$filename]);
	if ($idx)
	{
		echo '</tbody></table><p>total ',$idx;
	}
	else
	{
		echo '<p>nothing found';
	}
}
