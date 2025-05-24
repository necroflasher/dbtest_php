<?php

##
## file paths
##

# db created by f_stuff/4plebs_data_to_sqlite.py
const db_file_path = '/mnt/dancer/db/all.db';

# for features -> HAVE_ANALYZE
const f_tars_dir  = '/mnt/dancer';
const f_dbs_dir   = '/mnt/dancer/db';
const read_db_exe = '/mnt/dancer/code/f_tar_gz/read_db';
const analyze_exe = '/mnt/dancer/code/swf_big_analyze/analyze3';

##
## features
##

# - image dump tars exist in "f_tars_dir"
# - f_tar_gz databases of the tars exist in "f_dbs_dir"
# - read_db exe from f_tar_gz exists at "read_db_exe"
# - analyze exe from swf_big_analyze exists at "analyze_exe"
const HAVE_ANALYZE = true;

# all.db has the f_blacklist table
const HAVE_BLACKLIST = true;

# thumbnail dump exists and is accessible from F_THUMBS_PUBLIC
const HAVE_THUMBS = true;

##
## url paths
##

# should contain the dirs like "1394/" and so on
const F_THUMBS_PUBLIC = '/f_thumbs';

const DBTEST_DIR_PUBLIC    = '/dbtest';
const DBTEST_SCRIPT_PUBLIC = '/dbtest.php';

const MISSING_THUMB_PUBLIC = DBTEST_DIR_PUBLIC.'/im/q.png';
const HIDDEN_THUMB_PUBLIC  = DBTEST_DIR_PUBLIC.'/im/q.png';

##
## runtime config
##

# record time taken by each query
define('db_enable_query_stats', array_key_exists('stat', $_GET));

##
## external urls
##

function get_4plebs_search_url($md5)
{
	$md5 = md5_to_base64url($md5);
	return "https://archive.4plebs.org/f/search/image/$md5/";
}

function get_4plebs_ruffle_url($md5)
{
	$md5 = md5_to_base64url($md5);
	return "https://archive.4plebs.org/f/ruffle/$md5/#rufflecontainer";
}

function get_swfchan_search_url($filename, $filesize)
{
	$filename = urlencode($filename);

	# http://eye.swfchan.com/search/?q=
	# >if MiB is selected and the number entered into the size field is
	#  larger than 1024 it is assumed that the unit is bytes
	if ($filesize > 1024)
		return "http://eye.swfchan.com/search/?q=$filename&min=$filesize&max=$filesize";
	else
		return "http://eye.swfchan.com/search/?q=$filename&min=0&u1=k&max=1&u2=k";
}

function get_4plebs_post_url($threadnum, $postnum)
{
	return "https://archive.4plebs.org/f/thread/$threadnum/#$postnum";
}
