<?php

# want: count how many times it's been posted after that <-- could get this from f_reposts_meta
# ^ would make it easier to see what's potentially worth looking at

# want: buttons for prev/next month <-- need to get the min/max months, but not hard
# idea: put thumbs in a scrollable sidebar (multiple columns, highlight current one)
# idea: give threads a header bar with expand/collapse/next/prev links <-- fairly simple
# IDEA: flip thread (inside the page) to the next repost of that flash

# TODO: this could use pagination

# idea: sort by number of comments

# idea: visualize filesize and dimensions somehow?
# like draw the w/h frame?
# bar for how much of 10MB it uses?
# colors for something?

function fetch_reposts_for_month($month, $cb)
{
	db_foreach_row("
	SELECT
		fr.threadnum,
		fr.postnum,
		fr.filename,
		fr.timestamp,
		fr.tag,
		HEX(fr.md5) AS md5,
		fm.filesize,
		fm.width_px,
		fm.height_px,
		".sql_transform_md5_thumb_url('fm.md5', 'fm.thumb_filename')." AS thumb_url
	FROM f_reposts AS fr
	JOIN (SELECT MIN(postnum) AS min, MAX(postnum) AS max FROM f_reposts WHERE timestamp GLOB ?) rg
	JOIN f_reposts_meta AS fm ON fm.md5=fr.md5
	WHERE
		fr.postnum>=rg.min AND fr.postnum<=rg.max AND
		fr.timestamp=fm.first_seen
	ORDER BY fr.postnum ASC
	", $cb, ["$month*"]);
}

# https://web.archive.org/web/20101203013057/http://pastebin.com/4JVjS02b (line 683)
function fmt_size($bytes)
{
	if ($bytes >= 1024*1024)
		return round($bytes/(1024*1024), 2).' MB';
	else if ($bytes >= 1024)
		return round($bytes/1024).' KB';
	else
		return "$bytes B";
}

class PendingScripts
{
	function add_script($code)
	{
		$this->code[] = $code;
	}
	function output_scripts()
	{
		if (!empty($this->code))
		{
			echo '<script>';
			foreach ($this->code as $line)
			{
				echo $line;
			}
			echo '</script>';
			unset($this->code);
		}
	}
}

function firstsights_index()
{
	dbtest_write_header('first sights - dbtest');

	echo
		'<p>'.
			'This page lets you browse threads where a flash was posted for the first time.'.
			' '.
			'Pick a month to see all such "first sights" for that month.'.
		'<p>';

	$top = db_fetch_first_row("SELECT SUBSTR(MAX(first_seen), 1, 7) AS mo FROM f_reposts_meta")['mo'];
	$top_y = intval(substr($top, 0, 4));
	$top_m = intval(substr($top, 5, 2));

	$bot = db_fetch_first_row("SELECT SUBSTR(MIN(first_seen), 1, 7) AS mo FROM f_reposts_meta")['mo'];
	$bot_y = intval(substr($bot, 0, 4));
	$bot_m = intval(substr($bot, 5, 2));

	$q_count = new DbReusableQuery("
	SELECT COUNT() AS cnt
	FROM f_reposts_meta
	WHERE first_seen GLOB ?
	");

	for ($year = $top_y; $year >= $bot_y; $year--)
	{
		$startmonth = ($year === $top_y) ? $top_m : 12;
		$endmonth   = ($year === $bot_y) ? $bot_m : 1;

		for ($month = $startmonth; $month >= $endmonth; $month--)
		{
			$str = sprintf('%04d-%02d', $year, $month);

			echo
				'<a href="?do=firstsights&month=',$str,'">',$str,'</a>'.
				' '.
				'(',$q_count->fetch_first_row("$str*")['cnt'],')'.
				'<br>'.
				'';
		}
	}
}

function render_firstsights()
{
	db_init();

	$month = @strval($_GET['month']);

	if (!$month)
	{
		firstsights_index();
		return;
	}

	dbtest_write_header("$month first sights - dbtest");

	$q_comments_by_thread = new DbReusableQuery("
	SELECT ".ThreadRenderer::comment_columns_sql."
	FROM f_comments
	WHERE threadnum=?
	ORDER BY postnum ASC, subnum ASC
	");
	$foreach_comment = function ($threadnum, $cb) use ($q_comments_by_thread)
	{
		$q_comments_by_thread->foreach_row($cb, $threadnum);
	};

	$ps = new PendingScripts();

	fetch_reposts_for_month($_GET['month'], function ($row, $threadidx) use ($ps, $foreach_comment)
	{
		$threadnum     = $row['threadnum'];
		$postnum       = $row['postnum'];
		$timestamp     = $row['timestamp'];
		$filesize_fmt  = fmt_size($row['filesize']);
		$hexmd5        = $row['md5'];
		$filename_html = htmlspecialchars($row['filename']);
		$thumb_src     = $row['thumb_url'];
		$long_tag      = long_tags[$row['tag']];
		$width_px      = $row['width_px'];
		$height_px     = $row['height_px'];

		echo
			'<div class="thread">'.
				'<a href="?do=md5info&md5=',$hexmd5,'">'.
					'<img loading="lazy" alt="" title="',$filename_html,'" src="',$thumb_src,'" class="sidepic">'.
				'</a>'.
				'<div class="thread-info-text">'.
					'No. '.
					'<a href="',get_4plebs_post_url($threadnum, $postnum),'">',$postnum,'</a>'.
					'<span id="_t',$threadnum,'_subject_insert"></span>'.
					' (',$timestamp,')'.
					'<br>'.
					'File: <a href="?do=md5info&md5=',$hexmd5,'">',$filename_html,'</a>'.
					' (',$filesize_fmt,', ',$width_px,'x',$height_px,', ',$long_tag,')'.
				'</div>';

		$tr = new ThreadRenderer();
		$foreach_comment($threadnum, function ($postrow, $postidx) use ($tr)
		{
			$tr->render_post($postrow, $postidx);
		});
		if ($tr->subject !== null)
		{
			$json_insert = htmlspecialchars(substr(json_encode($tr->subject), 1, -1));
			$ps->add_script("document.getElementById(\"_t{$threadnum}_subject_insert\").innerHTML=\" - <b class=\\\"subject\\\">$json_insert</b>\";");
		}

		echo '</div>';

		if ($threadidx < 8 || ($threadidx > 0 && ($threadidx % 256) === 0))
		{
			$ps->output_scripts();
		}
	});

	$ps->output_scripts();
}
