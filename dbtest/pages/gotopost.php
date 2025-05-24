<?php

function render_gotopost()
{
	db_init();
	dbtest_write_header('jump to post - dbtest');

	# http://127.1.1.1/dbtest.php?do=gotopost&postnum=3460444
	# http://127.1.1.1/dbtest.php?do=gotopost&postnum=3460444123

	$postnum = intval($_GET['postnum']);

	$row = db_fetch_first_row("
	SELECT HEX(md5) AS md5, threadnum
	FROM f_reposts AS fr
	WHERE threadnum=? OR threadnum=(SELECT threadnum FROM f_comments WHERE postnum=? AND threadnum<>postnum)
	", [$postnum, $postnum]);

	if ($row)
	{
		$hash = ($row['threadnum'] !== $postnum)
			? '#com'.$postnum
			: '#t'.$postnum;
		echo '<meta http-equiv="refresh" content="0; url=?do=md5info&md5=', $row['md5'], $hash, '">';
	}
	else
	{
		echo '<p>post not found';
	}
}
