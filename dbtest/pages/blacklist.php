<?php

function get_thumb_url($filename)
{
	switch ($filename)
	{
		case null:
		case 'no_thumbnail': # from query [FIXME: is this necessary?]
			return MISSING_THUMB_PUBLIC;
		default:
			if (!HAVE_THUMBS)
			{
				return MISSING_THUMB_PUBLIC;
			}
			$tarpath = substr($filename, 0, 4).'/'.substr($filename, 4, 2).'/'.$filename;
			return F_THUMBS_PUBLIC."/$tarpath";
	}
}

function render_blacklist()
{
	db_init();
	dbtest_write_header('blacklisted flashes - dbtest');

	echo
		'<style>'.
			'SPAN[title] { border-bottom: thin dotted currentcolor; }'.
			'TD UL { margin: 7px; padding: 0px 20px; }'.
			'TR.level2 IMG[width="48"]:not(:hover) { filter: blur(3px); overflow: hidden; }'.
		'</style>'.
		'<script>'.
		'bigger = function ()'.
		'{'.
			'[].forEach.call(document.getElementsByClassName("badpic"), function (img) {'.
				'var sizes = [14, 48];'.
				'img.width = sizes[+!sizes.indexOf(+img.width)];'.
				'img.height = sizes[+!sizes.indexOf(+img.height)];'.
			'});'.
		'};'.
		'</script>'.
		'<p>'.
			'This page lists flashes that are blacklisted on the site.'.
			' '.
			'The exact meaning of this depends on the flash\'s <b>blacklisting level</b>.'.
		'<ul>'.
			'<li><b>Level 1</b> means it won\'t be included in randomized listings like the front page.</li>'.
			'<li><b>Level 2</b> means the above, plus hiding its thumbnail sitewide (except on this page).</li>'.
		'</ul>'.
		'<p>'.
			'The purpose of the blacklist is to keep the front page clean and interesting, and the rest of the site clean of the worst stuff.',
		'<p>'.
			'<a href="javascript:bigger()">Toggle larger images</a>'.
			' '.
			'<span title="Shows larger thumbnails. Level 2 images will be blurred, hover them to reveal.">(?)</a>'.
		'<table cellspacing="1" border="1">'.
			'<tr>'.
				'<th></th>'.
				'<th>MD5</th>'.
				'<th>Dim</th>'.
				'<th>Size</th>'.
				'<th>Lvl</th>'.
				'<th>Thumb</th>'.
				'<th><span title="Times posted">Cnt</span></th>'.
				'<th>First seen</th>'.
			'</tr>'.
			'';

	$prev_ts = null;
	db_foreach_row("
	SELECT
		fb.level,
		fm.reposts,
		fm.filesize,
		fm.first_seen,
		HEX(fb.md5) AS md5,
		fm.width_px||'x'||fm.height_px AS dim,
		fm.thumb_filename AS thumb_filename_orig,
		IFNULL(fm.thumb_filename, 'no_thumbnail') AS thumb_filename
	FROM f_blacklist AS fb
		JOIN f_reposts_meta AS fm ON fm.md5=fb.md5
	ORDER BY fb.rowid
	", function ($row) use (&$prev_ts) {
		if ($prev_ts !== null && $row['first_seen'] < $prev_ts)
		{
			echo '<tr colspan="6"><td colspan="8" style="height: 4px;"></td></tr>';
		}
		$prev_ts = $row['first_seen'];
		echo
			'<tr class="level',$row['level'],'">'.
				'<td>'.
					'<img class="badpic" width="14" height="14" src="',get_thumb_url($row['thumb_filename']),'">'.
				'</td>'.
				'<td>'.
					'<a href="?do=md5info&md5=',$row['md5'],'">'.
					'<code>'.
					'',$row['md5'],''.
					'</code>'.
					'</a>'.
				'</td>'.
				'<td>',$row['dim'],'</td>'.
				'<td align="right">',number_format($row['filesize']),'</td>'.
				'<td align="right">', $row['level'],'</td>'.
				'<td>',$row['thumb_filename_orig'],'</td>'.
				'<td align="right">',$row['reposts'],'</td>'.
				'<td>',$row['first_seen'],'</td>'.
			'</tr>';
	});

	echo
		'</table>'.
		'';
}
