<?php

# TODO: could be riced to get all threads and posts in one query (fast with the index)
# want: No. should link to that post <-- just do it
# idea: next/prev links for skipping threads <-- OR copy the headerbar idea from 1stsites
# idea: table of contents (thread link, number of comments, total number of words)

# TODO: port over use of PendingScripts from firstsights.php

function foreach_repost($md5, $cb)
{
	db_foreach_row("
	SELECT *
	FROM f_reposts
	WHERE md5=?
	ORDER BY postnum DESC
	", $cb, [[SQLITE3_BLOB, $md5]]);
}

function render_md5info()
{
	$md5 = decode_md5(@strval($_GET['md5']));
	if (!$md5)
	{
		http_response_code(404);
		dbtest_write_header('404 - dbtest');
		echo
			'<p><b>Error:</b> bad md5'.
			'<p>Accepted formats are:'.
			'<ul>'.
				'<li>32-character hex string'.
				'<li>22-character packed base64'.
				'<li>24-character base64'.
			'</ul>'.
			'<p>'.
			'For base64, both the standard and <tt>base64url</tt> formats are recognized.'
			;
		return;
	}

	db_init();

	#
	# meta info
	#
	$row = db_fetch_first_row("
	SELECT
		HEX(fm.md5) AS md5,
		fm.md5 AS md5_raw,
		filesize, width_px, height_px, first_seen, last_seen,
		".sql_transform_md5_thumb_url('fm.md5', 'fm.thumb_filename')." AS thumb_url,
		fr.filename,
		fm.data_filename
	FROM f_reposts_meta AS fm
		JOIN f_reposts AS fr
			ON fr.md5=fm.md5 AND fr.timestamp=fm.first_seen
	WHERE fm.md5=?
	", [[SQLITE3_BLOB, $md5]]);
	if (!$row)
	{
		http_response_code(404);
		dbtest_write_header('404 - dbtest');
		echo
			'<p><b>Error:</b> md5 not found in database'.
			'<p>Would you like to try:'.
			'<ul>'.
				'<li><a href="',get_4plebs_search_url($md5),'">search 4plebs</a>'.
			'</ul>';
		return;
	}

	dbtest_write_header('md5info - dbtest');
	echo
		'<a'.
			' href="',get_4plebs_ruffle_url($md5),'"'.
			' target="_blank"'.
		'>'.
			'<img'.
				' src="',$row['thumb_url'],'"'.
				' style="float: right;"'.
			'>'.
		'</a>'.
		'<p>'.
			'Size: ',number_format($row['filesize']),' bytes<br>'.
			'Dimensions: ',$row['width_px'],'x',$row['height_px'],'<br>'.
			'First seen: ',$row['first_seen'],'<br>'.
			'Last seen: ',$row['last_seen'],'<br>'.
			#'Dt: ',$row['data_filename'],'<br>'.
			'';

	#
	# names it's been posted as
	# WANT: mark first posted one
	#
	$topname = null;
	echo '<table class="seen-names">';
	db_foreach_row("
	SELECT COUNT() as cnt, filename
	FROM f_reposts
	WHERE md5=?
	GROUP BY filename
	ORDER BY cnt DESC, filename ASC
	", function ($x) use (&$topname) {
		if ($topname === null)
			$topname = $x['filename'];
		echo
			'<tr>'.
			'<td>',$x['cnt'],''.
			'<td>'.
			'<a href="?do=nameinfo&filename=',htmlspecialchars(urlencode($x['filename'])),'">',
				htmlspecialchars($x['filename']),
			'</a>';
	}, [[SQLITE3_BLOB, $md5]]);
	echo '</table>';

	if (HAVE_ANALYZE)
	echo
		'<p class="half">'.
		'Tools:'.
		' <a href="?do=analyze&md5=',strtoupper(bin2hex($md5)),'">analyze</a>';

	#
	# page title
	#
	# htmlentities causes entities to show up, seems to be safe with it removed?
	# - entities aren't parsed in script tags, neither are tags nor comments
	# - json_encode escapes slashes which you'd need to close the script tag
	# - json_encode escapes quotes which you'd need to close the string
	#
	$title_insert = json_encode($topname);
	echo
		'<script>document.title=',$title_insert,'+" - dbtest";</script>'.
		'<h2 style="font-size: inherit; border-bottom: thin dotted currentcolor;">Reposts</h2>'.
		'<p class="half">'.
		'Total <span id="top_stats_text">(loading)</span>'.
		'<p class="half">'.
		'Find more:'.
		' <a href="',get_4plebs_search_url($md5),'" target="_blank">4plebs</a>'.
		' <a href="',get_swfchan_search_url($topname, $row['filesize']),'" target="_blank">swfchan</a>'.
		'';

	$q_comments_by_thread = new DbReusableQuery("
	SELECT ".ThreadRenderer::comment_columns_sql."
	FROM f_comments
	WHERE threadnum=?
	ORDER BY postnum ASC, subnum ASC
	");
	$foreach_comment = function ($threadnum, $cb) use (&$q_comments_by_thread)
	{
		$q_comments_by_thread->foreach_row($cb, $threadnum);
	};

	$total_num_reposts = 0;
	$total_num_comments = 0;
	foreach_repost($md5, function ($row, $i) use (&$total_num_reposts, &$total_num_comments, $foreach_comment)
	{
		# info line
		echo
			'<div class="thread-listing-line" id="t',$row['threadnum'],'">'.
				'',$i+1,' of <span class="md5info-total-num-reposts">?</span>: '.
				'No. <a href="',get_4plebs_post_url($row['threadnum'], $row['postnum']),'">',$row['postnum'],'</a>'.
				'<span id="p',$row['postnum'],'_extra_text"></span>'.
			'</div>';

		# thread itself
		{
			$tr = new ThreadRenderer();
			$foreach_comment($row['postnum'], function ($postrow, $postidx) use ($tr)
			{
				$tr->render_post($postrow, $postidx);
			});
			$total_num_comments += $tr->num_comments;
			$total_num_reposts += 1;
		}

		# fixup script
		{
			$subject_str_insert = '';
			if ($tr->subject !== null)
			{
				$enc = htmlspecialchars(substr(json_encode($tr->subject), 1, -1));
				$subject_str_insert = " - <b class=\\\"subject\\\">$enc</b>";
			}
			$reply_plural = ($tr->num_replies === 1) ? 'reply' : 'replies' ;
			# if there are no comments, hide the empty OP post that says "no comment"
			# note: op_postnum can be null if the repost was a reply (so the thread wasn't found)
			$remove_op_stmt = (!$tr->num_comments && $tr->op_postnum !== null)
				? "document.getElementById(\"com{$tr->op_postnum}\").setAttribute(\"style\",\"display: none;\");"
				: '';
			echo
				'<script>'.
				'document.getElementById("p',$row['postnum'],'_extra_text").innerHTML="',$subject_str_insert,' (',$tr->num_replies,' ',$reply_plural,')";',
				$remove_op_stmt,
				'</script>';
		}
	});

	$repost_plural  = ($total_num_reposts  === 1) ? 'repost'  : 'reposts';
	$comment_plural = ($total_num_comments === 1) ? 'comment' : 'comments';
	echo
		'<script>'.
		'document.getElementById("top_stats_text").textContent="',$total_num_reposts,' ',$repost_plural,', ',$total_num_comments,' ',$comment_plural,'";'.
		'[].forEach.call(document.getElementsByClassName("md5info-total-num-reposts"),function(e){'.
			'e.textContent="',$total_num_reposts,'";'.
		'});'.
		'</script>';
}
